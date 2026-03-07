<?php

namespace App\Controller\Admin;

use App\Entity\Preset;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;

class PresetCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Preset::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('name'),
            TextField::new('resolution'),
            TextField::new('codec'),
            IntegerField::new('bitrate'),
        ];
    }
}
