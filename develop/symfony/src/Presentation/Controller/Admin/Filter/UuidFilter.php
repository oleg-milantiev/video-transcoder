<?php
declare(strict_types=1);

namespace App\Presentation\Controller\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Filter for UUID fields that casts to text for LIKE comparison.
 * Needed because PostgreSQL doesn't support LIKE on UUID columns directly.
 */
final class UuidFilter implements FilterInterface
{
    use FilterTrait;

    /**
     * @param TranslatableInterface|string|false|null $label
     */
    public static function new(string $propertyName, $label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(TextFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $alias = $filterDataDto->getEntityAlias();
        $property = $filterDataDto->getProperty();
        $parameterName = $filterDataDto->getParameterName();
        $value = $filterDataDto->getValue();

        $queryBuilder->andWhere(sprintf('CAST(%s.%s AS text) LIKE :%s', $alias, $property, $parameterName))
            ->setParameter($parameterName, '%' . $value . '%');
    }
}
