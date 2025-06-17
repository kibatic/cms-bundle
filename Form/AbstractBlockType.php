<?php

namespace Kibatic\CmsBundle\Form;

use Kibatic\CmsBundle\Entity\Block;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('slug', null, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Slug',
                    'title' => 'Warning : be very carefull when changing the slug as it could break the page you use this block !'
                ]
            ])
            ->add('language', LanguageType::class, [
                'label' => false,
                'required' => false,
                'preferred_choices' => empty($options['existing_languages']) ? ['fr', 'en'] : $options['existing_languages'],
                'placeholder' => 'Language',
                'duplicate_preferred_choices' => false,
                'empty_data' => null,
            ])
            ->add('content', null, [
                'label' => false,
                'attr' => [
                    'placeholder' => 'Content'
                ]
            ])
        ;
    }
    
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Block::class,
            'existing_languages' => [],
        ]);

        $resolver->setAllowedTypes('existing_languages', 'array');
    }

    public function getBlockPrefix()
    {
        return 'kibatic_cmsbundle_block';
    }
}
