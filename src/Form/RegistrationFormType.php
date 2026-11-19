<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\BirthdayType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('prenom', TextType::class, ['label' => 'Prénom', 'attr' => ['placeholder' => 'Votre prénom']])
            ->add('nom',    TextType::class, ['label' => 'Nom',    'attr' => ['placeholder' => 'Votre nom']])
            ->add('email',  EmailType::class, ['label' => 'Email', 'attr' => ['placeholder' => 'votre@email.com']])
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false, 'attr' => ['placeholder' => '+33 6 ...']])
            ->add('dateNaissance', BirthdayType::class, [
                'label'    => 'Date de naissance',
                'required' => false,
                'widget'   => 'single_text',
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => false,
                'first_options'   => ['label' => 'Mot de passe', 'attr' => ['placeholder' => 'Minimum 8 caractères']],
                'second_options'  => ['label' => 'Confirmer le mot de passe', 'attr' => ['placeholder' => 'Répétez le mot de passe']],
                'constraints'     => [
                    new NotBlank(['message' => 'Le mot de passe est obligatoire.']),
                    new Length(['min' => 8, 'minMessage' => 'Le mot de passe doit contenir au moins {{ limit }} caractères.']),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Client::class]);
    }
}
