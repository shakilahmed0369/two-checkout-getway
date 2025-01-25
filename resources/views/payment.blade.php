<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <script type="text/javascript" src="https://2pay-js.2checkout.com/v1/2pay.js"></script>
    <title>Document</title>
</head>

<body>
    <form type="post" id="payment-form" action="{{ route('payment.store') }}">
        
        <div class="row">
            <div class="col-12">
                <div class="input-group"><input type="text" id="name" placeholder="John Doe" minlength="3"
                        maxlength="50">
                    <label>Name</label>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-12" id="card-element">
            </div>
        </div>

        <div class="row">
            <div class="col-md-12"><input type="submit" id="submitPayment" value="Generate Token and Pay"
                    class="btn btn-primary placeicon">
            </div>
        </div>
    </form>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js" integrity="sha512-v2CJ7UaYy4JwqLDIrZUI/4hqeoQieOmAZNXBeQyjo21dadnwR+8ZaIJVT8EE2iyI61OV8e6M8PP2/4hpQINQ/g==" crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <script>
        
        window.addEventListener('load', function() {
            var jsPaymentClient = new TwoPayClient('255367460000'),
                component = jsPaymentClient.components.create('card');
            component.mount('#card-element');

            var doAjaxRequest = function(fparams) {
                $.ajax({
                    type: 'POST',
                    url: fparams.formAction,
                    data: {
                        ess_token: fparams.token,
                        testMode: true,
                        useCore: false,
                        refno: fparams.refno,
                        _token: "{{csrf_token()}}"
                    },
                    dataType: 'json',
                    xhrFields: {
                        withCredentials: true
                    }
                }).done(function(result) {
                    console.log('Ajax done result: ', result);
                    if (result.redirect) {
                        window.location.href = result.redirect;
                    } else if (result.success) {
                        alert(result.msg);
                    } else {
                        console.log(result.error);
                    }
                }).fail(function(response) {
                    alert(
                    'Your payment could not be processed. Please refresh the page and try again!');
                    console.log(response);
                });
            };

            var getFormParamsObj = function(formId) {
                return {
                    formAction: $('#' + formId).attr('action'),

                    // isTest: document.getElementById("testPayment").checked,
                    // useCore: document.getElementById("useCore").checked,
                    refno: ''
                }
            }

            //for placing orders with 2pay.js
            $('body').on('click', '#submitPayment', function(e) {
                console.log('Clicked on submitPayment!');
                e.preventDefault();
                var billingDetails = {
                    name: document.querySelector('#name').value,
                };
                var params = getFormParamsObj('payment-form');
                jsPaymentClient.tokens.generate(component, billingDetails).then(function(response) {
                    params.token = response.token;
                    doAjaxRequest(params);
                }).catch(function(error) {
                    alert(error);
                    console.log(error);
                });
            });

            //for placing orders with buy links
            $('body').on('click', '#submitBuyLinkRequest', function(e) {
                e.preventDefault();
                var params = getFormParamsObj('buylink-form');
                doAjaxRequest(params);
            });

            //for searching for order subscriptions
            $('body').on('click', '#submitOrderWithSubscription', function(e) {
                console.log('Clicked on Subscription!');
                e.preventDefault();
                var params = getFormParamsObj('subscrSrch-form');
                params.refno = $('#refno').val();
                doAjaxRequest(params);
            });

        });
    </script>
</body>

</html>
