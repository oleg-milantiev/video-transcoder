<?php

namespace App\Controller\Admin;

use App\Entity\Video;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;

class VideoCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Video::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('title'),
            ImageField::new('srcFilename', 'Video File')
                ->setBasePath('/uploads')
                ->setUploadDir('public/uploads')
                ->setRequired(true),
            ImageField::new('previewPath', 'Preview')
                ->setBasePath('/uploads')
                ->setUploadDir('public/uploads')
                ->setUploadedFileNamePattern('[uuid]/preview.[extension]'),
            ChoiceField::new('status')->setChoices([
                'Pending' => 'pending',
                'Processing' => 'processing',
                'Completed' => 'completed',
                'Failed' => 'failed',
            ]),
            AssociationField::new('user'),
            DateTimeField::new('createdAt')->hideOnForm(),
            DateTimeField::new('updatedAt')->hideOnForm(),
        ];
    }
}
