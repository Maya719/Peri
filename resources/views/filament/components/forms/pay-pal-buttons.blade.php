<div
    x-data="{
        init() {
            this.renderPaypal()
        },
        renderPaypal() {
            let container = this.$refs.buttonContainer
            container.innerHTML = ''

            if (typeof paypal !== 'undefined') {
                paypal.Buttons({
                    createSubscription: (data, actions) => {
                        return actions.subscription.create({
                            plan_id: '{{ $getPlan() }}',
                        })
                    },
                    onApprove: (data, actions) => {
                        let form = document.createElement('form')
                        form.method = 'POST'
                        form.action = '{{ route('payments.callback', 'Paypal') }}'

                        let csrf = document.createElement('input')
                        csrf.type = 'hidden'
                        csrf.name = '_token'
                        csrf.value = '{{ csrf_token() }}'
                        form.appendChild(csrf)

                        let sub = document.createElement('input')
                        sub.type = 'hidden'
                        sub.name = 'subscription_id'
                        sub.value = data.subscriptionID
                        form.appendChild(sub)

                        let trx = document.createElement('input')
                        trx.type = 'hidden'
                        trx.name = 'trx'
                        trx.value = '{{ $getPayTrx() }}'
                        form.appendChild(trx)

                        document.body.appendChild(form)
                        form.submit()
                    }
                }).render(container)
            }
        }
    }"
    x-cloak
    wire:ignore
>
    <div x-ref="buttonContainer"></div>
    @once
        <script src="https://www.paypal.com/sdk/js?client-id={{ $getClient() }}&vault=true&intent=subscription"></script>
    @endonce
</div>
