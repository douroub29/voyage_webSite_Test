<?php

namespace App\Form\Admin;

use App\Entity\Chauffeur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class ChauffeurFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom',           TextType::class, ['label' => 'Nom'])
            ->add('prenom',        TextType::class, ['label' => 'Prénom'])
            ->add('telephone',     TelType::class,  ['label' => 'Téléphone'])
            ->add('voiture',       TextType::class, ['label' => 'Véhicule (marque, modèle)'])
            ->add('prixTransfert', MoneyType::class, ['label' => 'Prix du transfert (€)', 'currency' => 'EUR'])
            ->add('photoFile', FileType::class, [
                'label'    => 'Photo',
                'mapped'   => false,
                'required' => false,
                'constraints' => [new Image(['maxSize' => '3M'])],
            ])
            ->add('actif', CheckboxType::class, ['label' => 'Chauffeur actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Chauffeur::class]);
    }
}
