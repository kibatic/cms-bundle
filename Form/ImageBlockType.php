<?php

namespace Kibatic\CmsBundle\Form;

use Symfony\Component\Form\FormBuilderInterface;

class ImageBlockType extends AbstractBlockType implements BlockTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        $builder->add('content', null, [
            'attr' => [
                'placeholder' => 'Image URL'
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'kibatic_cmsbundle_imageblock';
    }

    public static function getBlockTypeName(): string
    {
        return 'image';
    }
}
