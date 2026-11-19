<?php

namespace App\Form\Admin;

use App\Entity\Destination;
use App\Entity\Hotel;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class HotelFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom',         TextType::class,    ['label' => "Nom de l'hôtel"])
            ->add('adresse',     TextType::class,    ['label' => 'Adresse'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'attr' => ['rows' => 5]])
            ->add('destination', EntityType::class, [
                'class'        => Destination::class,
                'label'        => 'Destination',
                'choice_label' => fn(Destination $d) => $d->getNom() . ' (' . $d->getPays() . ')',
            ])
            ->add('prixParNuit', MoneyType::class, ['label' => 'Prix par nuit (€)', 'currency' => 'EUR'])
            ->add('etoiles', ChoiceType::class, [
                'label'   => 'Étoiles',
                'choices' => ['1 étoile' => 1, '2 étoiles' => 2, '3 étoiles' => 3, '4 étoiles' => 4, '5 étoiles' => 5],
            ])
            ->add('photoPrincipaleFile', FileType::class, [
                'label'    => 'Photo principale',
                'mapped'   => false,
                'required' => false,
                'constraints' => [new Image(['maxSize' => '5M'])],
            ])
            ->add('actif', CheckboxType::class, ['label' => 'Hôtel actif / visible', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Hotel::class]);
    }
}
