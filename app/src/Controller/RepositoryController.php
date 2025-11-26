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

#[Route('/admin/repository', name: 'repository_')]
class RepositoryController extends AbstractController
{
    public function __construct(
        private readonly RepositoryConfigRepository $repositoryConfigRepository,
        private readonly DocumentNodeRepository $documentNodeRepository,
        private readonly SyncLogRepository $syncLogRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('/', name: 'index')]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, GitHubRepositoryValidator $validator, TokenCipher $cipher): Response
    {
        // this is temporary because we want to work with only 1 repository config at first. 
        // TODO : update this to handle multi repository (maybe in Lot 3)
        $config = $this->repositoryConfigRepository->findOneBy([]) ?? new RepositoryConfig();
        $token = $config->getToken();

        $form = $this->createForm(RepositoryConfigType::class, $config);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();

            if($token === $data->getToken()) {
                $token = $cipher->decrypt($token);
            } else {
                $token = $data->getToken();
            }

            try {
                $validator->assertRepositoryIsReachable($data->getOwner(), $data->getName(), $token);
            } catch (\Exception $e) {
                $this->addFlash('error', 'It seems there is a token issue here.');
                return $this->redirectToRoute('repository_index');
            }
            
            $data->setEncryptedToken($cipher->encrypt($token));

            $this->em->persist($data);
            $this->em->flush();

            $this->addFlash('success', 'Configuration saved. Tokens are redacted after persistence.');

            return $this->redirectToRoute('repository_index');
        }

        return $this->render('repository/config.html.twig', [
            'form' => $form->createView(),
            'config' => $config,
            'token_preview' => $config->getEncryptedToken() !== '' ? $config->getRedactedToken($cipher->decrypt($config->getEncryptedToken())) : null,
        ]);
    }

    #[Route('/tree', name: 'tree')]
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

    #[Route('/sync', name: 'sync', methods: ['POST'])]
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
