<?php

namespace AppBundle;

use AppBundle\Entity\User;
use Doctrine\ORM\EntityManager;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;

class StripeClient
{
    /**
     * @var EntityManager
     */
    private $em;

    /**
     * StripeClient constructor.
     *
     * @param $secretKey
     * @param EntityManager $em
     */
    public function __construct($secretKey, EntityManager $em)
    {
        $this->em = $em;

        Stripe::setApiKey($secretKey);
    }

    public function createCustomer(User $user, $paymentToken)
    {
        $customer = Customer::create([
            'email' => $user->getEmail(),
            'source' => $paymentToken
        ]);

        $user->setStripeCustomerId($customer->id);

        $this->em->persist($user);
        $this->em->flush();

        return $customer;
    }

    public function updateCustomerCard(User $user, $paymentToken)
    {
        $customer = Customer::retrieve($user->getStripeCustomerId());
        $customer->source = $paymentToken;
        $customer->save();
    }

    public function createInvoiceItem($amount, User $user, $description)
    {
        InvoiceItem::create(
            array(
                "amount" => $amount * 100,
                "currency" => "usd",
                "customer" => $user->getStripeCustomerId(),
                "description" => $description
            )
        );
    }

    public function createInvoice(User $user, $payImmediately = true)
    {
        $invoice = Invoice::create(
            array(
                "customer" => $user->getStripeCustomerId()
            )
        );

        if ($payImmediately) {
            $invoice->pay();
        }

        return $invoice;
    }
}