<?php

namespace App\Form;

use App\Entity\Game;
use App\Entity\Tournament;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TournamentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre del torneo',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Descripcion',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                ],
            ])
            ->add('format', ChoiceType::class, [
                'label' => 'Formato',
                'choices' => [
                    'Eliminacion simple' => 'single_elim',
                    'Suizo' => 'swiss',
                    'Round robin' => 'round_robin',
                ],
            ])
            ->add('status', ChoiceType::class, [
                'label' => 'Estado',
                'choices' => [
                    'Borrador' => 'draft',
                    'Abierto' => 'open',
                    'En curso' => 'running',
                    'Finalizado' => 'completed',
                    'Cancelado' => 'canceled',
                ],
            ])
            ->add('maxParticipants', ChoiceType::class, [
                'label' => 'Cupo maximo',
                'choices' => [
                    '4' => 4,
                    '8' => 8,
                    '16' => 16,
                    '32' => 32,
                    '64' => 64,
                ],
            ])
            ->add('startAt', null, [
                'label' => 'Fecha de inicio',
                'widget' => 'single_text',
            ])
            ->add('game', EntityType::class, [
                'label' => 'Juego',
                'class' => Game::class,
                'choice_label' => 'name',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tournament::class,
        ]);
    }
}
