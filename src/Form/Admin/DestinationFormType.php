<?php

namespace App\Form\Admin;

use App\Entity\Destination;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Image;

class DestinationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom',         TextType::class,     ['label' => 'Nom de la destination'])
            ->add('pays',        TextType::class,     ['label' => 'Pays'])
            ->add('description', TextareaType::class, ['label' => 'Description', 'attr' => ['rows' => 5]])
            ->add('photoFile',   FileType::class, [
                'label'    => 'Photo (JPG, PNG, WEBP)',
                'mapped'   => false,
                'required' => false,
                'constraints' => [new Image(['maxSize' => '5M'])],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Destination::class]);
    }
}
