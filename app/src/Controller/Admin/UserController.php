<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\PasswordResetType;
use App\Form\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Manage user accounts for administrators (CRUD and password resets).
 */
#[Route('/admin/users', name: 'admin_user_')]
#[IsGranted('ROLE_ADMIN')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TranslatorInterface $translator
    ) {
    }

    /**
     * List registered users.
     */
    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $users = $this->userRepository->findBy([], ['email' => 'ASC']);

        return $this->render('admin/user/index.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Create a new user.
     */
    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $user = new User();
        $form = $this->createForm(UserType::class, $user, ['include_password' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('user.flash.created'));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    /**
     * Edit roles and status for an existing user.
     */
    #[Route('/{id}/edit', name: 'edit')]
    public function edit(User $user, Request $request): Response
    {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', $this->translator->trans('user.flash.updated'));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Reset a user's password with admin-provided value.
     */
    #[Route('/{id}/reset-password', name: 'reset_password')]
    public function resetPassword(User $user, Request $request): Response
    {
        $form = $this->createForm(PasswordResetType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            $hashedPassword = $this->passwordHasher->hashPassword($user, $plainPassword);
            $user->setPassword($hashedPassword);

            $this->entityManager->flush();

            $this->addFlash('success', $this->translator->trans('user.flash.password_reset'));

            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('admin/user/reset_password.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }
}
