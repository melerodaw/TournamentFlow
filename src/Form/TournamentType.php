<?php

namespace App\Form;

use App\Entity\Game;
use App\Entity\Tournament;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TournamentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nombre del torneo',
                'constraints' => [
                    new Assert\NotBlank(message: 'El nombre del torneo es obligatorio.'),
                    new Assert\Length(min: 3, minMessage: 'El nombre debe tener al menos {{ limit }} caracteres.'),
                ],
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
            ->add('maxParticipants', ChoiceType::class, [
                'label' => 'Cupo maximo',
                'choices' => [
                    '4' => 4,
                    '8' => 8,
                    '16' => 16,
                    '32' => 32,
                    '64' => 64,
                ],
                'constraints' => [
                    new Assert\NotBlank(message: 'El numero maximo de participantes es obligatorio.'),
                    new Assert\GreaterThanOrEqual(value: 2, message: 'El torneo debe permitir al menos 2 participantes.'),
                ],
            ])
            ->add('startAt', null, [
                'label' => 'Fecha de inicio',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(message: 'La fecha del torneo es obligatoria.'),
                    new Assert\GreaterThan('now', message: 'La fecha del torneo debe ser futura.'),
                ],
            ])
            ->add('registrationDeadlineAt', null, [
                'label' => 'Fecha limite de inscripcion',
                'widget' => 'single_text',
                'constraints' => [
                    new Assert\NotBlank(message: 'La fecha limite de inscripcion es obligatoria.'),
                    new Assert\GreaterThan('now', message: 'La fecha limite de inscripcion debe ser futura.'),
                ],
            ])
            ->add('game', EntityType::class, [
                'label' => 'Juego',
                'class' => Game::class,
                'choice_label' => 'name',
                'constraints' => [
                    new Assert\NotNull(message: 'Debes seleccionar un juego.'),
                ],
            ])
            ->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event): void {
                $form = $event->getForm();
                $deadline = $form->get('registrationDeadlineAt')->getData();
                $startAt = $form->get('startAt')->getData();

                if ($deadline instanceof \DateTimeInterface && $startAt instanceof \DateTimeInterface && $deadline > $startAt) {
                    $form->get('registrationDeadlineAt')->addError(new FormError('La fecha limite de inscripcion no puede ser posterior a la fecha del torneo.'));
                }
            })
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Tournament::class,
        ]);
    }
}
