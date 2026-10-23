<?php

namespace Kibatic\CmsBundle\Form;

use Kibatic\CmsBundle\Navigation\NavigationContent;
use Kibatic\CmsBundle\Repository\BlockRepository;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Exception\TransformationFailedException;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Navigation: a tree of entries rendered as a menu (Navigation\NavigationContent). Its content field is the JSON written
 * by the navigation editor (block/_navigation_editor.html.twig).
 */
class NavigationBlockType extends AbstractBlockType implements BlockTypeInterface
{
    public function __construct(
        private readonly BlockRepository $blocks,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        parent::buildForm($builder, $options);
        // Shared by every language (Block::SHARED_TYPES): saved without language
        $builder->remove('language');

        $builder->add('content', HiddenType::class, [
            'label' => false,
            'attr' => ['data-kcms-navigation-content' => true],
            // Translated in the "validators" domain, like every error of a form
            'invalid_message' => 'kibatic_cms.navigation.invalid',
        ]);

        // Saved normalized: at least one entry, each one with a label, linking to existing blocks (no navigation block)
        $builder->get('content')->addModelTransformer(new CallbackTransformer(
            fn (?string $content) => $content,
            function (?string $json): string {
                $navigation = NavigationContent::fromJson($json);

                if ([] === $navigation->items || $navigation->hasUnlabelledItem()) {
                    throw new TransformationFailedException('Empty navigation', invalidMessage: 'kibatic_cms.navigation.empty');
                }

                $types = [];
                foreach ($this->blocks->findBy(['slug' => $navigation->slugs()]) as $block) {
                    $types[$block->getSlug()] = $block->getType();
                }

                foreach ($navigation->slugs() as $slug) {
                    if (!isset($types[$slug]) || self::getBlockTypeName() === $types[$slug]) {
                        throw new TransformationFailedException('Unknown block', invalidMessage: 'kibatic_cms.navigation.invalid');
                    }
                }

                return $navigation->toJson();
            },
        ));
    }

    public function getBlockPrefix(): string
    {
        return 'kibatic_cmsbundle_navigationblock';
    }

    public static function getBlockTypeName(): string
    {
        return 'navigation';
    }
}
