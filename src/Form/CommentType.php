<?php

namespace App\Form;

use App\Entity\Comment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('content', TextareaType::class, [
            'label' => 'Ton petit mot',
            // Champ vide : chaîne vide plutôt que null, pour obtenir le message de validation
            'empty_data' => '',
            'attr' => ['rows' => 3, 'maxlength' => Comment::MAX_LENGTH, 'placeholder' => 'Écris un petit mot pour Sarah…'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Comment::class]);
    }
}
