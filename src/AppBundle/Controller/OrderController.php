<?php

namespace AppBundle\Controller;

use AppBundle\Entity\Product;
use AppBundle\Entity\User;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Stripe\Charge;
use Stripe\Customer;
use Stripe\Invoice;
use Stripe\InvoiceItem;
use Stripe\Stripe;
use Symfony\Component\HttpFoundation\Request;

class OrderController extends BaseController
{
    /**
     * @Route("/cart/product/{slug}", name="order_add_product_to_cart")
     * @Method("POST")
     */
    public function addProductToCartAction(Product $product)
    {
        $this->get('shopping_cart')
            ->addProduct($product);

        $this->addFlash('success', 'Product added!');

        return $this->redirectToRoute('order_checkout');
    }

    /**
     * @Route("/checkout", name="order_checkout", schemes={"%secure_channel%"})
     * @Security("is_granted('ROLE_USER')")
     */
    public function checkoutAction(Request $request)
    {
        $products = $this->get('shopping_cart')->getProducts();

        if ($request->isMethod('POST')) {

            $token = $request->request->get('stripeToken');

            $stripeClient = $this->get('stripe_client');

            /** @var User $user */
            $user = $this->getUser();

            if (!$user->getStripeCustomerId()) {
                $stripeClient->createCustomer($user, $token);
            } else {
                $stripeClient->updateCustomerCard($user, $token);
            }

            /*Charge::create(array(
                "amount" => $this->get('shopping_cart')->getTotal() * 100,
                "currency" => "usd",
                "customer" => $user->getStripeCustomerId(),
                "description" => "First test charge!"
            ));*/

            foreach ($this->get('shopping_cart')->getProducts() as $product) {
                $stripeClient->createInvoiceItem(
                    $product->getPrice(),
                    $user,
                    $product->getName()
                );
            }

            $stripeClient->createInvoice($user, true);

            $this->get('shopping_cart')->emptyCart();
            $this->addFlash('success', 'Order Complete! Yay!');

            return $this->redirectToRoute('homepage');
        }

        return $this->render('order/checkout.html.twig', array(
            'products' => $products,
            'cart' => $this->get('shopping_cart'),
            'stripe_public_key' => $this->getParameter('stripe_public_key')
        ));

    }
}