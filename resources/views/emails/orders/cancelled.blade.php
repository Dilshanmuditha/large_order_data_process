@component('mail::message')
Important: Order Cancelled

Dear Customer,

We regret to inform you that your recent order #{{ $orderId }} could not be completed and has been cancelled.
Order Total : ${{ $total }}
Customer ID : ${{ $customerId }}

Best regards,
The Support Team
@endcomponent