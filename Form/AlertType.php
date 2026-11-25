<?php

namespace Kibatic\CmsBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class AlertType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'form.alert.type',
                'choices' => [
                    'form.alert.success' => 'success',
                    'form.alert.info' => 'info',
                    'form.alert.warning' => 'warning',
                    'form.alert.danger' => 'danger',
                ]
            ])
            ->add('message', TextareaType::class, [
                'label' => 'form.alert.message',
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    public function getBlockPrefix(): string
    {
        return 'kibatic_cmsbundle_alertblock';
    }
}
