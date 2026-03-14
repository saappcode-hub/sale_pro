<!-- Hidden inputs for contact_id and business_id -->
<input type="hidden" name="contact_id" id="contact_id" value="{{$contact->id}}">
<input type="hidden" name="business_id" id="business_id" value="{{$contact->business_id}}">

<div class="shipping_address_body">
    @include('contact.partials.shipping_address_table')
</div>