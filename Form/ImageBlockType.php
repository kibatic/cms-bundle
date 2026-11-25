<?php

namespace Kibatic\CmsBundle\Form;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;

class ImageBlockType extends AbstractBlockType implements BlockTypeInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);

        // Text input rather than UrlType: relative paths (/images/…) are valid
        $builder->add('content', TextType::class, [
            'label' => 'form.image_url.label',
            'attr' => [
                'inputmode' => 'url',
                'placeholder' => 'form.image_url.placeholder',
                'data-kcms-image-url' => true,
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
