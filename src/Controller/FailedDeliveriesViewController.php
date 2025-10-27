<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\FailedDelivery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Failed Deliveries View Controller
 * Handles failed CDP deliveries view for administrators
 */
class FailedDeliveriesViewController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {}

    #[Route('/failed-deliveries', name: 'failed_deliveries_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request): Response
    {
        $page = max(1, (int)($request->query->get('page', 1)));
        $limit = min(100, max(1, (int)($request->query->get('limit', 20))));
        $status = $request->query->get('status');
        $cdpSystemName = $request->query->get('cdp_system_name');
        $createdFrom = $request->query->get('created_from');
        $createdTo = $request->query->get('created_to');

        // Build query
        $repository = $this->entityManager->getRepository(\App\Model\FailedDelivery::class);
        $qb = $repository->createQueryBuilder('fd')
            ->leftJoin('fd.lead', 'l')
            ->leftJoin('l.customer', 'c')
            ->addSelect('l')
            ->addSelect('c');

        // Apply filters
        if ($status) {
            $qb->andWhere('fd.status = :status')
                ->setParameter('status', $status);
        }
        if ($cdpSystemName) {
            $qb->andWhere('fd.cdpSystemName = :cdpSystemName')
                ->setParameter('cdpSystemName', $cdpSystemName);
        }
        if ($createdFrom) {
            $qb->andWhere('fd.createdAt >= :createdFrom')
                ->setParameter('createdFrom', new \DateTime($createdFrom));
        }
        if ($createdTo) {
            $qb->andWhere('fd.createdAt <= :createdTo')
                ->setParameter('createdTo', new \DateTime($createdTo . ' 23:59:59'));
        }

        // Get total count
        $countQb = clone $qb;
        $totalCount = (int)$countQb->select('COUNT(fd.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination
        $offset = ($page - 1) * $limit;
        $failedDeliveries = $qb->orderBy('fd.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Build pagination
        $lastPage = (int)ceil($totalCount / $limit);
        $from = $totalCount > 0 ? (($page - 1) * $limit) + 1 : 0;
        $to = min($page * $limit, $totalCount);
        
        $pagination = [
            'currentPage' => $page,
            'perPage' => $limit,
            'total' => $totalCount,
            'lastPage' => $lastPage,
            'from' => $from,
            'to' => $to,
            'hasNext' => $page < $lastPage,
            'hasPrevious' => $page > 1,
        ];

        $filters = [
            'status' => $status,
            'cdp_system_name' => $cdpSystemName,
            'created_from' => $createdFrom,
            'created_to' => $createdTo,
        ];

        return $this->render('failed_deliveries/index.html.twig', [
            'failedDeliveries' => $failedDeliveries,
            'pagination' => $pagination,
            'filters' => $filters,
        ]);
    }
    
    #[Route('/failed-deliveries/count', name: 'failed_deliveries_count', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function count(): Response
    {
        $repository = $this->entityManager->getRepository(FailedDelivery::class);
        $count = (int)$repository->createQueryBuilder('fd')
            ->select('COUNT(fd.id)')
            ->where('fd.status IN (:statuses)')
            ->setParameter('statuses', ['pending', 'retrying', 'failed'])
            ->getQuery()
            ->getSingleScalarResult();
        
        if ($count === 0) {
            return new Response('');
        }
        
        return new Response("<span class='badge bg-danger ms-2'>{$count}</span>");
    }

    #[Route('/failed-deliveries/details/{id}', name: 'failed_deliveries_details', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function details(int $id): Response
    {
        $failedDelivery = $this->entityManager->getRepository(FailedDelivery::class)
            ->find($id);

        if (!$failedDelivery) {
            throw new NotFoundHttpException('Failed delivery not found');
        }

        return $this->render('failed_deliveries/_details.html.twig', [
            'failedDelivery' => $failedDelivery,
        ]);
    }
}

