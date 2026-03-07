terraform {
  required_providers {
    yandex = { source = "yandex-cloud/yandex" }
  }
}

provider "yandex" {
  token     = var.yc_token
  cloud_id  = var.cloud_id
  folder_id = var.folder_id
  zone      = var.zone
}

data "yandex_compute_image" "coi" {
  family = "container-optimized-image"
}

# --- СЕТЬ ---
resource "yandex_vpc_network" "k8s-net" { name = "k8s-network" }

resource "yandex_vpc_gateway" "nat-gw" {
  name = "nat-gw"
  shared_egress_gateway {}
}

resource "yandex_vpc_route_table" "rt" {
  network_id = yandex_vpc_network.k8s-net.id
  static_route {
    destination_prefix = "0.0.0.0/0"
    gateway_id         = yandex_vpc_gateway.nat-gw.id
  }
}

resource "yandex_vpc_subnet" "k8s-subnet" {
  zone           = var.zone
  network_id     = yandex_vpc_network.k8s-net.id
  v4_cidr_blocks = ["10.5.0.0/24"]
  route_table_id = yandex_vpc_route_table.rt.id
}

# --- МАСТЕР-НОДА (Server) ---
resource "yandex_compute_instance" "k3s-master" {
  name        = "k3s-master"
  platform_id = "standard-v3"
  resources {
    cores  = 2
    memory = 4
  }
  boot_disk {
    initialize_params {
      image_id = data.yandex_compute_image.coi.id
    }
  } # Ubuntu 22.04
  network_interface {
    subnet_id          = yandex_vpc_subnet.k8s-subnet.id
    nat                = true # Внешний IP нужен только мастеру для управления
    ip_address         = "10.5.0.10"
    security_group_ids = [yandex_vpc_security_group.k3s-sg.id]
  }
  metadata = {
    user-data = templatefile("${path.module}/master-init.tftpl", { k3s_token = var.k3s_token })
    ssh-keys  = "ubuntu:${file("id_rsa.pub")}"
  }
}

# --- ВОРКЕР-ГРУППА (Agents) ---
resource "yandex_iam_service_account" "sa" { name = "k3s-sa" }
resource "yandex_resourcemanager_folder_iam_member" "editor" {
  folder_id = var.folder_id
  role      = "editor"
  member    = "serviceAccount:${yandex_iam_service_account.sa.id}"
}

resource "yandex_compute_instance_group" "k3s-workers" {
  name               = "k3s-workers"
  folder_id          = var.folder_id
  service_account_id = yandex_iam_service_account.sa.id

  depends_on = [
    yandex_resourcemanager_folder_iam_member.editor,
    yandex_compute_instance.k3s-master
  ]

  instance_template {
    platform_id = "standard-v3"
    resources {
      cores  = 2
      memory = 4
    }
    boot_disk {
      initialize_params {
        image_id = data.yandex_compute_image.coi.id
        size     = 30
      }
    }
    network_interface {
      network_id         = yandex_vpc_network.k8s-net.id
      subnet_ids         = [yandex_vpc_subnet.k8s-subnet.id]
      security_group_ids = [yandex_vpc_security_group.k3s-sg.id]
    }
    metadata = {
      user-data = templatefile("${path.module}/worker-init.tftpl", {
        master_ip = "10.5.0.10",
        k3s_token = var.k3s_token
      })
      ssh-keys = "ubuntu:${file("id_rsa.pub")}"
    }
    scheduling_policy { preemptible = true }
  }
  scale_policy {
    auto_scale {
      initial_size           = 1
      max_size               = 3
      cpu_utilization_target = 70
      measurement_duration   = 60
    }
  }
  allocation_policy { zones = [var.zone] }
  deploy_policy {
    max_unavailable = 1
    max_expansion   = 1
  }
  load_balancer { target_group_name = "k3s-tg" }
}

# --- NETWORK LOAD BALANCER ---
resource "yandex_lb_network_load_balancer" "k3s-lb" {
  name = "k3s-lb"
  listener {
    name = "http"
    port = 80
    external_address_spec { ip_version = "ipv4" }
  }
  attached_target_group {
    target_group_id = yandex_compute_instance_group.k3s-workers.load_balancer[0].target_group_id
    healthcheck {
      name = "tcp"
      tcp_options {
        port = 80
      }
    } # Traefik healthcheck
  }
}

resource "yandex_vpc_security_group" "k3s-sg" {
  name       = "k3s-security-group"
  network_id = yandex_vpc_network.k8s-net.id

  # Разрешаем SSH для ваших IP
  ingress {
    protocol       = "TCP"
    port           = 22
    v4_cidr_blocks = var.admin_ips
  }

  # Разрешаем доступ к API Kubernetes (порт 6443) для ваших IP
  ingress {
    protocol       = "TCP"
    port           = 6443
    v4_cidr_blocks = var.admin_ips
  }

  # Разрешаем HTTP для всех (через балансировщик)
  ingress {
    protocol       = "TCP"
    port           = 80
    v4_cidr_blocks = ["0.0.0.0/0"]
  }

  # Разрешаем весь трафик внутри сети (между мастером и воркерами)
  ingress {
    protocol          = "ANY"
    from_port         = 0
    to_port           = 65535
    predefined_target = "self_security_group"
  }

  # Разрешаем все исходящие (чтобы качать образы)
  egress {
    protocol       = "ANY"
    from_port      = 0
    to_port        = 65535
    v4_cidr_blocks = ["0.0.0.0/0"]
  }
}

resource "null_resource" "get_kubeconfig" {
  depends_on = [yandex_compute_instance.k3s-master]

  provisioner "local-exec" {
    command = <<EOT
      MAX_RETRIES=60
      COUNT=0
      MASTER_IP="${yandex_compute_instance.k3s-master.network_interface[0].nat_ip_address}"

      echo "Waiting for k3s.yaml to appear on $MASTER_IP..."

      while [ $COUNT -lt $MAX_RETRIES ]; do
        if scp -o StrictHostKeyChecking=no -o ConnectTimeout=5 ubuntu@$MASTER_IP:/etc/rancher/k3s/k3s.yaml ./k3s_config.yaml 2>/dev/null; then
          echo "Success: k3s_config.yaml downloaded."
          # Меняем IP на внешний
          sed -i "s/127.0.0.1/$MASTER_IP/g" ./k3s_config.yaml
          exit 0
        fi

        echo "File not found yet, retrying in 5s... ($((COUNT+1))/$MAX_RETRIES)"
        sleep 5
        COUNT=$((COUNT+1))
      done

      echo "Error: Failed to download k3s.yaml after 5 minutes."
      exit 1
    EOT
  }
}

output "master_ip" { value = yandex_compute_instance.k3s-master.network_interface.0.nat_ip_address }
output "lb_ip" { value = flatten(yandex_lb_network_load_balancer.k3s-lb.listener[*].external_address_spec[*].address)[0] }
