<?php

namespace Kibatic\CmsBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class AlertBlockType extends AbstractBlockType implements BlockTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        parent::buildForm($builder, $options);

        $builder
            ->add('content', AlertType::class, [
                'label' => false
            ])
        ;
    }

    public function getBlockPrefix()
    {
        return 'kibatic_cmsbundle_alertblock';
    }

    public static function getBlockTypeName(): string
    {
        return 'alert';
    }
}
