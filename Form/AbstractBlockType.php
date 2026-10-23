<?php

namespace Kibatic\CmsBundle\Form;

use Kibatic\CmsBundle\Entity\Block;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\LanguageType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class AbstractBlockType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('slug', null, [
                'label' => 'form.slug.label',
                'help' => 'form.slug.help',
                'attr' => [
                    'placeholder' => 'form.slug.placeholder',
                    'spellcheck' => 'false',
                ]
            ])
            ->add('language', LanguageType::class, [
                'label' => 'form.language.label',
                'help' => 'form.language.help',
                'required' => false,
                'preferred_choices' => empty($options['existing_languages']) ? ['fr', 'en'] : $options['existing_languages'],
                'placeholder' => 'form.language.placeholder',
                'duplicate_preferred_choices' => false,
                'empty_data' => null,
            ])
            ->add('content', null, [
                'label' => 'form.content',
            ])
            ->add('styles', TextareaType::class, [
                'label' => 'form.styles.label',
                'help' => 'form.styles.help',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'spellcheck' => 'false',
                    'placeholder' => "display: grid; gap: 16px;\nh2 { color: var(--brand-color); }",
                    'data-kcms-styles' => true,
                ],
            ])
        ;
    }
    
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Block::class,
            'translation_domain' => 'KibaticCmsBundle',
            'existing_languages' => [],
        ]);

        $resolver->setAllowedTypes('existing_languages', 'array');
    }

    public function getBlockPrefix(): string
    {
        return 'kibatic_cmsbundle_block';
    }
}
