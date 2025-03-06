<?php

namespace Kibatic\CmsBundle\Form;

use Kibatic\CmsBundle\Entity\Block;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
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
            'data_class' => Block::class
        ]);
    }

    public function getBlockPrefix()
    {
        return 'kibatic_cmsbundle_block';
    }
}
