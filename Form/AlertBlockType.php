<?php

namespace Kibatic\CmsBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class AlertBlockType extends AbstractBlockType implements BlockTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('content', AlertType::class, [
                'label' => 'form.alert.label'
            ])
        ;
    }

    public function getBlockPrefix(): string
    {
        return 'kibatic_cmsbundle_alertblock';
    }

    public static function getBlockTypeName(): string
    {
        return 'alert';
    }
}
