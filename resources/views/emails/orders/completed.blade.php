@component('mail::message')
Important: Order successfully

Dear Customer,

We happy to inform you that your recent order #{{ $orderId }} is successfully completed.
Order Total : ${{ $total }}
Customer ID : ${{ $customerId }}

Best regards,
The Support Team
@endcomponent