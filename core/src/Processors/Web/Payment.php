<?php

namespace App\Processors\Web;

use App\Model\Order;

class Payment extends \App\Processor
{

    public function post()
    {
        $this->container->logger->error('Payment!', ['data' => $this->getProperties()]);

        // Robokassa
        if ($id = (int)$this->getProperty('InvId')) {
            /** @var Order $order */
            if ($order = Order::query()->find($id)) {
                $service = new \App\Service\Payment\Robokassa($this->container);
                if ($service->checkCrc($this->getProperties())) {
                    $order->status = 2;
                    $order->paid_at = date('Y-m-d H:i:s');
                    $order->paid_till = date('Y-m-d H:i:s', strtotime("+$order->period month"));
                    $order->save();

                    return $this->success('Ok!');
                }
            }
        }

        return $this->failure('Error');
    }


    protected function sendEmail(array $data) {

    }

}