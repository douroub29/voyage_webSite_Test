<?php

namespace App\Form;

use App\Entity\Chauffeur;
use App\Entity\Hotel;
use App\Entity\Reservation;
use App\Enum\TypeTransfert;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReservationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $voyage = $options['voyage'];
        $places = $voyage->getPlacesRestantes();

        $builder
            ->add('nombrePersonnes', IntegerType::class, [
                'label' => 'Nombre de personnes',
                // Pas d'attribut "max" : le message d'erreur clair (JS + serveur) est affiché
                // au lieu de la bulle native du navigateur.
                'attr'  => ['min' => 1, 'data-places' => $places],
                'invalid_message' => 'Veuillez saisir un nombre entier de personnes.',
                'constraints' => [
                    new Assert\NotBlank(message: 'Indiquez le nombre de personnes.'),
                    new Assert\LessThanOrEqual(
                        value: $places,
                        message: sprintf(
                            'Nombre de personnes ({{ value }}) supérieur au nombre de places disponibles : il ne reste que %d place(s) pour ce voyage.',
                            $places
                        ),
                    ),
                ],
            ])
            ->add('hotel', EntityType::class, [
                'class'        => Hotel::class,
                'label'        => 'Hôtel (optionnel)',
                'required'     => false,
                'placeholder'  => '-- Sans hôtel --',
                'query_builder' => function (EntityRepository $er) use ($voyage): QueryBuilder {
                    return $er->createQueryBuilder('h')
                        ->where('h.destination = :dest')
                        ->andWhere('h.actif = true')
                        ->setParameter('dest', $voyage->getDestination())
                        ->orderBy('h.etoiles', 'DESC');
                },
                'choice_label' => fn(Hotel $h) => $h->getNom() . ' (' . $h->getEtoiles() . '★) - ' . $h->getPrixParNuit() . ' €/nuit',
            ])
            ->add('chauffeur', EntityType::class, [
                'class'       => Chauffeur::class,
                'label'       => 'Chauffeur de transfert (optionnel)',
                'required'    => false,
                'placeholder' => '-- Sans transfert --',
                'query_builder' => fn(EntityRepository $er) => $er->createQueryBuilder('c')
                    ->where('c.actif = true')
                    ->orderBy('c.nom', 'ASC'),
                'choice_label' => fn(Chauffeur $c) => $c->getNomComplet() . ' - ' . $c->getVoiture() . ' (' . $c->getPrixTransfert() . ' €)',
            ])
            ->add('typeTransfert', EnumType::class, [
                'class'    => TypeTransfert::class,
                'label'    => 'Type de transfert',
                'required' => false,
                'placeholder' => '-- Choisir --',
                'choice_label' => fn(TypeTransfert $t) => $t->label(),
            ])
            ->add('notes', TextareaType::class, [
                'label'    => 'Demandes particulières',
                'required' => false,
                'attr'     => ['rows' => 3, 'placeholder' => 'Allergies, préférences, besoins spéciaux...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Reservation::class]);
        $resolver->setRequired(['voyage']);
    }
}
