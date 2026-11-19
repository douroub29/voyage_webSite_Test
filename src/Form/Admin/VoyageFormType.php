<?php

namespace App\Form\Admin;

use App\Entity\Destination;
use App\Entity\Societe;
use App\Entity\Voyage;
use Doctrine\ORM\EntityRepository;
use App\Enum\CompagnieAerienne;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class VoyageFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre',       TextType::class,    ['label' => 'Titre du voyage'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'attr' => ['rows' => 5]])
            ->add('destination', EntityType::class, [
                'class'        => Destination::class,
                'label'        => 'Destination',
                'choice_label' => fn(Destination $d) => $d->getNom() . ' (' . $d->getPays() . ')',
            ])
            ->add('societe', EntityType::class, [
                'class'         => Societe::class,
                'label'         => 'Société de voyage',
                'required'      => false,
                'placeholder'   => '-- Aucune --',
                'choice_label'  => 'nom',
                'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('s')->orderBy('s.nom', 'ASC'),
            ])
            ->add('dateDepart', DateType::class, ['label' => 'Date de départ', 'widget' => 'single_text'])
            ->add('dateRetour', DateType::class, ['label' => 'Date de retour',  'widget' => 'single_text'])
            ->add('prix', MoneyType::class, ['label' => 'Prix par personne (€)', 'currency' => 'EUR'])
            ->add('compagnieAerienne', EnumType::class, [
                'class'        => CompagnieAerienne::class,
                'label'        => 'Compagnie aérienne',
                'placeholder'  => '-- Choisir la compagnie --',
                'choice_label' => fn(CompagnieAerienne $c) => $c->label() . ' (' . $c->codeIata() . ')',
            ])
            ->add('numeroVol', TextType::class, [
                'label'    => 'Numéro de vol',
                'required' => false,
                'attr'     => ['placeholder' => 'Ex : TU 712', 'maxlength' => 10],
            ])
            ->add('nombrePlaces', IntegerType::class, [
                'label' => 'Capacité de l\'avion (nombre de places)',
                'attr'  => ['min' => 1, 'max' => Voyage::CAPACITE_MAX_AVION],
                'help'  => 'Les places disponibles sont calculées automatiquement à chaque réservation / annulation.',
            ])
            ->add('photoFile', FileType::class, [
                'label'    => 'Photo',
                'mapped'   => false,
                'required' => false,
                'constraints' => [new Image(['maxSize' => '5M'])],
            ])
            ->add('actif', CheckboxType::class, ['label' => 'Voyage actif / visible', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Voyage::class]);
    }
}
