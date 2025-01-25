<?php
namespace App\Http\Controllers;

require_once(base_path('/plugin/2checkout-php-sdk/autoloader.php'));
use Illuminate\Http\Request;
use Tco\Examples\Common;
use Tco\TwocheckoutFacade;
use Exception;

class PaymentController extends Controller
{

    function index() {
        return view('payment'); 
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

        $orderParams   = new Common\OrderParams\DynamicProducts();
        $exampleConfig = [
            'sellerId'      => '255367460000', // REQUIRED
            'secretKey'     => 'DfKI|0+mCF~t3!w@k=gQ', // REQUIRED
            'buyLinkSecretWord'    => '',
            'jwtExpireTime' => 30,
            'curlVerifySsl' => 1
        ];
        $result        = null;

        try {
            $tco        = new TwocheckoutFacade($exampleConfig);
            $token      = $validated['ess_token'];
            $testMode   = $validated['testMode'];
            $useCore    = $validated['useCore'];

            $predefinedDynamicOrderParams = $orderParams->getDynamicProductSuccessParams();
            $originalPaymentDetails       = $predefinedDynamicOrderParams['PaymentDetails'];

            $newPaymentDetails = $this->getPaymentDetailsWith3DsUrls($token, $testMode);
            $mergedPaymentDetails = array_merge_recursive($originalPaymentDetails, $newPaymentDetails);

            $predefinedDynamicOrderParams['PaymentDetails'] = $mergedPaymentDetails;

            // Place the order via API or Core API
            if (!$useCore) {
                $response = $tco->order()->place($predefinedDynamicOrderParams);
            } else {
                $response = $tco->apiCore()->call('/orders/', $predefinedDynamicOrderParams);
            }

            // Handle API response
            if (!isset($response['RefNo'])) {
                $result = [
                    'success' => false,
                    'errors'  => $response['message'] ?? 'An unknown error occurred.',
                ];
            } elseif (isset($response['Errors']) && !empty($response['Errors'])) {
                $errorMessage = implode(PHP_EOL, $response['Errors']);
                $result = [
                    'success' => false,
                    'error'   => $errorMessage,
                ];
            } else {
                $hasAuthorize3ds = $response['PaymentDetails']['PaymentMethod']['Authorize3DS'] ?? false;
                $redirectTo = $hasAuthorize3ds ? $this->hasAuthorize3DS($hasAuthorize3ds) : route('payment.success', [
                    'refno' => $response['RefNo'],
                ]);

                $result = [
                    'success'  => true,
                    'refno'    => $response['RefNo'],
                    'redirect' => $redirectTo,
                ];
            }
        } catch (Exception $exception) {
            $result = [
                'success' => false,
                'errors'  => $exception->getMessage(),
            ];
        }

        return response()->json($result);
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
    private function hasAuthorize3DS(array $has3ds): ?string
    {
        if (isset($has3ds['Href']) && !empty($has3ds['Href'])) {
            return $has3ds['Href'] . '?avng8apitoken=' . ($has3ds['Params']['avng8apitoken'] ?? '');
        }

        return null;
    }

    function success(Request $request){
        $exampleConfig = [
            'sellerId'      => '255367460000', // REQUIRED
            'secretKey'     => 'DfKI|0+mCF~t3!w@k=gQ', // REQUIRED
            'buyLinkSecretWord'    => '',
            'jwtExpireTime' => 30,
            'curlVerifySsl' => 1
        ];

        $tco = new TwocheckoutFacade($exampleConfig);

        try {
            $response = $tco->order()->getOrder(['RefNo' => $request->refno]);
            dd($response);
        } catch (Exception $e) {
            throw $e;
            return null;
        } 
    }
}
