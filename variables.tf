variable "yc_token" {
  default = "y0__xCDlpGFqveAAhjB3RMgj7uMzhaj_l0VTH3Mnx2IRoMKDEt09eA_Hg"
}

variable "cloud_id" {
  default = "b1ga87dgstflgqffu01k"
}

variable "folder_id" {
  default = "b1gn9234k0q6gnk69p3t"
}

variable "zone" {
  default = "ru-central1-a"
}

variable "db_password" {
  default = "12345678"
}

variable "deploy_bastion" {
  type    = bool
  default = true # Измените на false, чтобы полностью удалить бастион и сэкономить
}

variable "k3s_token" {
  default = "secret_token_123" # Нужен для подключения воркеров к мастеру
}

variable "admin_ips" {
  type        = list(string)
  description = "Список ваших IP (в формате 1.2.3.4/32) для управления"
  default     = ["212.237.218.136/32", "45.8.91.169/32"]
}
