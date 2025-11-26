<?php

namespace App\Controller;

use App\Entity\RepositoryConfig;
use App\Form\RepositoryConfigType;
use App\Repository\DocumentNodeRepository;
use App\Repository\RepositoryConfigRepository;
use App\Repository\SyncLogRepository;
use App\Service\GitHubRepositoryValidator;
use App\Service\GitHubSyncService;
use App\Service\TokenCipher;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class RepositoryController extends AbstractController
{
    public function __construct(
        private readonly RepositoryConfigRepository $repositoryConfigRepository,
        private readonly DocumentNodeRepository $documentNodeRepository,
        private readonly SyncLogRepository $syncLogRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/admin/repository', name: 'repository_config')]
    #[IsGranted('ROLE_ADMIN')]
    public function configure(Request $request, GitHubRepositoryValidator $validator, TokenCipher $cipher): Response
    {
        // this is temporary because we want to work with only 1 repository config at first. 
        // TODO : update this to handle multi repository (maybe in Lot 3)
        $config = $this->repositoryConfigRepository->findOneBy([]) ?? new RepositoryConfig();
        $form = $this->createForm(RepositoryConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $validator->assertRepositoryIsReachable($data->getOwner(), $data->getName(), $data->getToken());
            $data->setEncryptedToken($cipher->encrypt($data->getToken()));

            $this->em->persist($data);
            $this->em->flush();

            $this->addFlash('success', 'Configuration saved. Tokens are redacted after persistence.');

            return $this->redirectToRoute('repository_config');
        }

        return $this->render('repository/config.html.twig', [
            'form' => $form->createView(),
            'config' => $config,
            'token_preview' => $config->getEncryptedToken() !== '' ? $config->getRedactedToken($cipher->decrypt($config->getEncryptedToken())) : null,
        ]);
    }

    #[Route('/repository/tree', name: 'repository_tree')]
    public function tree(): Response
    {
        // this is temporary because we want to work with only 1 repository config at first. 
        // TODO : update this to handle multi repository (maybe in Lot 3)
        $config = $this->repositoryConfigRepository->findOneBy([]);
        if (!$config) {
            return $this->render('repository/empty.html.twig');
        }

        $nodes = $this->documentNodeRepository->findByRepository($config);
        $logs = $this->syncLogRepository->findBy(['repositoryConfig' => $config], ['startedAt' => 'DESC'], 10);

        return $this->render('repository/tree.html.twig', [
            'config' => $config,
            'nodes' => $nodes,
            'logs' => $logs,
        ]);
    }

    #[Route('/repository/sync', name: 'repository_sync', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function sync(GitHubSyncService $syncService): RedirectResponse
    {
        $config = $this->repositoryConfigRepository->findOneBy([]);
        if (!$config) {
            $this->addFlash('error', 'Configure a repository first.');
            return $this->redirectToRoute('repository_tree');
        }

        $syncService->syncRepository($config, $this->getUser()?->getUserIdentifier() ?? 'anonymous');
        $this->addFlash('success', 'Sync dispatched.');

        return $this->redirectToRoute('repository_tree');
    }
}
