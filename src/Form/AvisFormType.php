<?php

namespace App\Form;

use App\Entity\Avis;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AvisFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('note', ChoiceType::class, [
                'label'    => 'Votre note',
                'choices'  => ['5 ★★★★★' => 5, '4 ★★★★' => 4, '3 ★★★' => 3, '2 ★★' => 2, '1 ★' => 1],
                'expanded' => false,
            ])
            ->add('commentaire', TextareaType::class, [
                'label' => 'Votre avis',
                'attr'  => ['rows' => 4, 'minlength' => 10, 'maxlength' => 1000, 'placeholder' => 'Partagez votre expérience avec DreamTravel...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Avis::class]);
    }
}
