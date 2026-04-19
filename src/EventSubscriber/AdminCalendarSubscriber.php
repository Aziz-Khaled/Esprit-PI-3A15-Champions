<?php

namespace App\EventSubscriber;

use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use CalendarBundle\CalendarEvents;
use CalendarBundle\Entity\Event;
use CalendarBundle\Event\CalendarEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AdminCalendarSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly OrderRepository $orderRepository
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CalendarEvents::SET_DATA => 'onCalendarSetData',
        ];
    }

    public function onCalendarSetData(CalendarEvent $calendar): void
    {
        $start = $calendar->getStart();
        $end = $calendar->getEnd();

        $productQb = $this->productRepository->createQueryBuilder('p')
            ->where('p.createdAt IS NOT NULL')
            ->andWhere('p.createdAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        foreach ($productQb->getQuery()->getResult() as $product) {
            $event = new Event(
                sprintf('Product: %s', $product->getName() ?? 'Unnamed'),
                $product->getCreatedAt()
            );

            $event->setOptions([
                'backgroundColor' => '#1cc88a',
                'borderColor' => '#1cc88a',
                'textColor' => '#ffffff',
            ]);

            $calendar->addEvent($event);
        }

        $orderQb = $this->orderRepository->createQueryBuilder('o')
            ->where('o.orderDate IS NOT NULL')
            ->andWhere('o.orderDate BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        foreach ($orderQb->getQuery()->getResult() as $order) {
            $date = $order->getOrderDate();
            if (!$date) {
                continue;
            }

            $event = new Event(
                sprintf('Order #%d (%s BTC)', $order->getId(), number_format((float) $order->getTotalAmount(), 2)),
                $date,
                (clone $date)->modify('+1 hour')
            );

            $event->setOptions([
                'backgroundColor' => '#4e73df',
                'borderColor' => '#4e73df',
                'textColor' => '#ffffff',
            ]);

            $calendar->addEvent($event);
        }
    }
}
