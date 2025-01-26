<?php

namespace App\Http\Controllers;

require_once(base_path('/plugin/2checkout-php-sdk/autoloader.php'));

use Illuminate\Http\Request;
use Tco\Examples\Common;
use Tco\TwocheckoutFacade;
use Exception;

class PaymentController extends Controller
{
    /**
     * Display the payment page.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('payment');
    }

    /**
     * Get 2Checkout API configuration.
     *
     * @return array
     */
    private function getTcoConfig(): array
    {
        return [
            'sellerId'          => '255367460000', // From .env file
            'secretKey'         => 'DfKI|0+mCF~t3!w@k=gQ', // From .env file
            'buyLinkSecretWord' => env('TCO_BUY_LINK_SECRET_WORD', ''),
            'jwtExpireTime'     => 30,
            'curlVerifySsl'     => true,
        ];
    }

    /**
     * Generate dynamic order array.
     *
     * @param array $overrides
     * @return array
     */
    private function generateOrderArray(array $overrides = []): array
    {
        $defaultOrder = [
            'Country'           => 'US',
            'Currency'          => 'USD',
            'CustomerIP'        => request()->ip(),
            'ExternalReference' => 'CustOrd' . now()->timestamp,
            'Language'          => 'en',
            'BillingDetails'    => [
                'Address1'    => 'Street 1',
                'City'        => 'Cleveland',
                'State'       => 'Ohio',
                'CountryCode' => 'US',
                'Email'       => 'testcustomer@2Checkout.com',
                'FirstName'   => 'John',
                'LastName'    => 'Doe',
                'Zip'         => '20034',
            ],
            'Items'             => [
                [
                    'Name'         => 'Colored Pencil',
                    'Description'  => 'Test description',
                    'Quantity'     => 1,
                    'IsDynamic'    => true,
                    'Tangible'     => false,
                    'PurchaseType' => 'PRODUCT',
                    'Price'        => [
                        'Amount' => 2,
                        'Type'   => 'CUSTOM',
                    ],
                ],
            ],
            'PaymentDetails'    => [
                'Currency'      => 'USD',
                'CustomerIP'    => request()->ip(),
                'PaymentMethod' => [
                    'RecurringEnabled' => false,
                ],
            ],
        ];

        return array_replace_recursive($defaultOrder, $overrides);
    }

    /**
     * Handles 3DS payment flow with 2PayJs token and places the order.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function processPayment(Request $request)
    {
        $validated = $request->validate([
            'ess_token' => 'required|string',
            'testMode'  => 'required',
            'useCore'   => 'required',
        ]);

        $tco = new TwocheckoutFacade($this->getTcoConfig());

        try {
            $predefinedDynamicOrderParams = $this->generateOrderArray([
                'PaymentDetails' => $this->getPaymentDetailsWith3DsUrls(
                    $validated['ess_token'],
                    $validated['testMode']
                ),
            ]);

            $response = $validated['useCore']
                ? $tco->apiCore()->call('/orders/', $predefinedDynamicOrderParams)
                : $tco->order()->place($predefinedDynamicOrderParams);

            if (isset($response['Errors']) && !empty($response['Errors'])) {
                return $this->errorResponse(implode(PHP_EOL, $response['Errors']));
            }

            $redirectTo = isset($response['PaymentDetails']['PaymentMethod']['Authorize3DS'])
                ? $this->extract3DSUrl($response['PaymentDetails']['PaymentMethod']['Authorize3DS'])
                : route('payment.success', ['refno' => $response['RefNo']]);

            return response()->json([
                'success'  => true,
                'refno'    => $response['RefNo'],
                'redirect' => $redirectTo,
            ]);
        } catch (Exception $exception) {
            return $this->errorResponse($exception->getMessage());
        }
    }

    /**
     * Generates 3DS callback URLs.
     *
     * @param string $action
     * @param int|null $cartId
     * @return string
     */
    private function get3DsCallbackUrl(string $action, ?int $cartId = null): string
    {
        $params = [
            'action' => $action,
            'cartId' => $cartId ?? now()->timestamp,
        ];

        return Common\Helpers\MyHelper::generateUrl('Redirect3ds', $params);
    }

    /**
     * Gets payment details with 3DS URLs.
     *
     * @param string $token
     * @param bool $testMode
     * @return array
     */
    private function getPaymentDetailsWith3DsUrls(string $token, bool $testMode): array
    {
        return [
            'Type'          => $testMode ? 'TEST' : 'EES_TOKEN_PAYMENT',
            'PaymentMethod' => [
                'EesToken'           => $token,
                'Vendor3DSReturnURL' => $this->get3DsCallbackUrl('success'),
                'Vendor3DSCancelURL' => $this->get3DsCallbackUrl('cancel'),
            ],
        ];
    }

    /**
     * Extracts the 3DS authorization URL.
     *
     * @param array $has3ds
     * @return string|null
     */
    private function extract3DSUrl(array $has3ds): ?string
    {
        return $has3ds['Href'] . '?avng8apitoken=' . ($has3ds['Params']['avng8apitoken'] ?? '');
    }

    /**
     * Error response helper.
     *
     * @param string $message
     * @return \Illuminate\Http\JsonResponse
     */
    private function errorResponse(string $message)
    {
        return response()->json([
            'success' => false,
            'errors'  => $message,
        ]);
    }

    /**
     * Handles payment success callback.
     *
     * @param Request $request
     * @return void
     */
    public function success(Request $request)
    {
        $tco = new TwocheckoutFacade($this->getTcoConfig());

        try {
            $response = $tco->order()->getOrder(['RefNo' => $request->refno]);
            dd($response);
        } catch (Exception $e) {
            throw $e;
        }
    }
}
