<?php

namespace App;

use DB;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Contact extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'shipping_custom_field_details' => 'array',
    ];

    /**
     * Get the business that owns the user.
     */
    public function business()
    {
        return $this->belongsTo(\App\Business::class);
    }

    public function scopeActive($query)
    {
        return $query->where('contacts.contact_status', 'active');
    }
   public function contactMap()
{
    return $this->hasOne(ContactMap::class, 'contact_id', 'id');
}
    public function contactMaps()
    {
        // Assumes 'contact_id' in 'contacts_map' table refers to 'id' in 'contacts' table
        return $this->hasOne(ContactMap::class, 'contact_id', 'id');
    }

    // add by Sokha not remove this line
    public function contactShippingAddress()
    {
        return $this->hasOne(ShippingAddress::class, 'contact_id', 'id');
    }
    public function getLastShippingAddressMap()
    {
        return $this->contactShippingAddresses()->latest('id')->value('map');
    }

    /**
     * Filters only own created suppliers or has access to the supplier
     */
    public function scopeOnlySuppliers($query)
    {
        if (auth()->check() && ! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $query->whereIn('contacts.type', ['supplier', 'both']);
      //   $query->orWhere('contacts.is_default', 1);

        if (auth()->check() && ! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
            $query->where(function ($q) {
                $user_id = auth()->user()->id;
                $q->where('contacts.created_by', $user_id)
                    ->orWhere('ucas.user_id', $user_id);
            });
        }

        return $query;
    }

    /**
     * Filters only own created customers or has access to the customer
     */
    public function scopeOnlyCustomers($query)
    {
        //Commented because of issue in woocommerce sync
        // if (auth()->check() && !auth()->user()->can('customer.view') && !auth()->user()->can('customer.view_own')) {
        //     abort(403, 'Unauthorized action.');
        // }

        // Error on call API list
        $query->whereIn('contacts.type', ['customer', 'both']);
        //$query->whereNull('contacts.deleted_at');
      //   $query->orWhere('contacts.is_default', 1);

        if (auth()->check() && ! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            // Skip created_by restriction for zone-access users:
            //   zone_access_all = 1  → user sees ALL contacts (no restriction)
            //   zone_access_all = NULL + zones assigned → zone WHERE in indexCustomer() is authority
            //   zone_access_all = NULL + no zones → indexCustomer() shows nothing via whereRaw('1=0')
            // Only apply created_by restriction for plain view_own users with no zone setup.
            $isZoneUser = (auth()->user()->zone_access_all == 1) ||
                          (\App\UserLocationZone::where('user_id', auth()->user()->id)->exists());

            if (!$isZoneUser) {
                $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
                $query->where(function ($q) {
                    $user_id = auth()->user()->id;
                    $q->where('contacts.created_by', $user_id)
                        ->orWhere('ucas.user_id', $user_id);
                });
            }
       }

        return $query;
    }

    /**
     * Filters only own created contact or has access to the contact
     */
    public function scopeOnlyOwnContact($query)
    {
        $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
        $query->select('*');
        $query->selectRaw('contacts.id as id');
        $query->where(function ($q) {
            $user_id = auth()->user()->id;
            $q->where('contacts.created_by', $user_id)
                ->orWhere('contacts.is_default', 1)
                ->orWhere('ucas.user_id', $user_id);
        });

        return $query;
    }

    /**
     * Filters only own created contact or has access to the contact
     */
    public function scopeOnlyOwnContactFilter($query)
    {
      if(request()->type){
         $query->whereIn('contacts.type', [request()->type, 'both']);
      }

        $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
        $query->select('*');
        $query->selectRaw('contacts.id as id');
        $query->where(function ($q) {
            $user_id = auth()->user()->id;
            $q->where('contacts.created_by', $user_id)
                ->orWhere('contacts.is_default', 1)
                ->orWhere('ucas.user_id', $user_id);
        });

        return $query;
    }

    /**
     * Get all of the contacts's notes & documents.
     */
    public function documentsAndnote()
    {
        return $this->morphMany(\App\DocumentAndNote::class, 'notable');
    }

    /**
     * Return list of contact dropdown for a business
     *
     * @param $business_id int
     * @param $exclude_default = false (boolean)
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function contactDropdown($business_id, $exclude_default = false, $prepend_none = true, $append_id = true)
    {
        $query = Contact::where('business_id', $business_id)
                    ->where('type', '!=', 'lead')
                    ->active();

        if ($exclude_default) {
            $query->where('is_default', 0);
        }

        if ($append_id) {
            $query->select(
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', name, CONCAT(name, ' - ', COALESCE(supplier_business_name, ''), '(', contacts.contact_id, ')')) AS supplier"),
                'contacts.id'
                    );
        } else {
            $query->select(
                'contacts.id',
                DB::raw("IF (supplier_business_name IS not null, CONCAT(name, ' (', supplier_business_name, ')'), name) as supplier")
            );
        }

        if (auth()->check() && ! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            $query->leftjoin('user_contact_access AS ucas', 'contacts.id', 'ucas.contact_id');
            $query->where(function ($q) {
                $user_id = auth()->user()->id;
                $q->where('contacts.created_by', $user_id)
                    ->orWhere('ucas.user_id', $user_id);
            });
        }

        $contacts = $query->pluck('supplier', 'contacts.id');

        //Prepend none
        if ($prepend_none) {
            $contacts = $contacts->prepend(__('lang_v1.none'), '');
        }

        return $contacts;
    }

    /**
     * Return list of suppliers dropdown for a business
     *
     * @param $business_id int
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function suppliersDropdown($business_id, $prepend_none = true, $append_id = true)
    {
        $all_contacts = Contact::where('contacts.business_id', $business_id)
                        ->whereIn('contacts.type', ['supplier', 'both'])
                        ->active();

        if ($append_id) {
            $all_contacts->select(
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', name, CONCAT(contacts.name, ' - ', COALESCE(contacts.supplier_business_name, ''), '(', contacts.contact_id, ')')) AS supplier"),
                'contacts.id'
                    );
        } else {
            $all_contacts->select(
                'contacts.id',
                DB::raw("CONCAT(contacts.name, ' (', contacts.supplier_business_name, ')') as supplier")
                );
        }

        if (auth()->check() && ! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            $all_contacts->onlyOwnContact();
        }

        $suppliers = $all_contacts->pluck('supplier', 'id');

        //Prepend none
        if ($prepend_none) {
            $suppliers = $suppliers->prepend(__('lang_v1.none'), '');
        }

        return $suppliers;
    }

    /**
     * Return list of customers dropdown for a business
     *
     * @param $business_id int
     * @param $prepend_none = true (boolean)
     * @return array users
     */
    public static function customersDropdown($business_id, $prepend_none = true, $append_id = true)
    {
        $query = Contact::where('contacts.business_id', $business_id)
                        ->whereIn('contacts.type', ['customer', 'both'])
                        ->active();

        // Apply permission scope if necessary
        if (auth()->check() && !auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            $query->onlyOwnContact();
        }

        // CRITICAL FIX: Explicitly specify the 'contacts' table for each column
        // This removes the "ambiguous column" error.
        $contacts_list = $query->select(
            'contacts.id',
            'contacts.name',
            'contacts.supplier_business_name',
            'contacts.contact_id'
        )->get();

        // Use a PHP collection method to build the dropdown, which is safer than raw SQL
        $customers = $contacts_list->mapWithKeys(function ($contact) use ($append_id) {
            if (!$append_id) {
                return [$contact->id => $contact->name ?? ''];
            }

            // Build the display name part by part
            $businessName = $contact->supplier_business_name ?? '';
            $contactName = $contact->name ?? '';
            $contactId = $contact->contact_id;
            
            $displayName = '';

            if (!empty($businessName)) {
                $displayName = $businessName;
            }

            if (!empty($contactName)) {
                $displayName .= ($displayName ? ' - ' : '') . $contactName;
            }
            
            if (empty($displayName)) {
                $displayName = 'Unknown Customer';
            }

            if (!empty($contactId)) {
                $displayName .= ' (' . $contactId . ')';
            }

            return [$contact->id => $displayName];
        });

        if ($prepend_none) {
            $customers = $customers->prepend(__('lang_v1.none'), '');
        }

        return $customers;
    }

    /**
     * Return list of contact type.
     *
     * @param $prepend_all = false (boolean)
     * @return array
     */
    public static function typeDropdown($prepend_all = false)
    {
        $types = [];

        if ($prepend_all) {
            $types[''] = __('lang_v1.all');
        }

        $types['customer'] = __('report.customer');
        $types['supplier'] = __('report.supplier');
        $types['both'] = __('lang_v1.both_supplier_customer');

        return $types;
    }

    /**
     * Return list of contact type by permissions.
     *
     * @return array
     */
    public static function getContactTypes()
    {
        $types = [];
        if (auth()->check() && auth()->user()->can('supplier.create')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->check() && auth()->user()->can('customer.create')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->check() && auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        return $types;
    }

    public function getContactAddressAttribute()
    {
        $address_array = [];
        if (! empty($this->supplier_business_name)) {
            $address_array[] = $this->supplier_business_name;
        }
        if (! empty($this->name)) {
            $address_array[] = ! empty($this->supplier_business_name) ? '<br>'.$this->name : $this->name;
        }
        if (! empty($this->address_line_1)) {
            $address_array[] = '<br>'.$this->address_line_1;
        }
        if (! empty($this->address_line_2)) {
            $address_array[] = '<br>'.$this->address_line_2;
        }
        if (! empty($this->city)) {
            $address_array[] = '<br>'.$this->city;
        }
        if (! empty($this->state)) {
            $address_array[] = $this->state;
        }
        if (! empty($this->country)) {
            $address_array[] = $this->country;
        }

        $address = '';
        if (! empty($address_array)) {
            $address = implode(', ', $address_array);
        }
        if (! empty($this->zip_code)) {
            $address .= ',<br>'.$this->zip_code;
        }

        return $address;
    }

    public function getFullNameAttribute()
    {
        $name_array = [];
        if (! empty($this->prefix)) {
            $name_array[] = $this->prefix;
        }
        if (! empty($this->first_name)) {
            $name_array[] = $this->first_name;
        }
        if (! empty($this->middle_name)) {
            $name_array[] = $this->middle_name;
        }
        if (! empty($this->last_name)) {
            $name_array[] = $this->last_name;
        }

        return implode(' ', $name_array);
    }

    public function getFullNameWithBusinessAttribute()
    {
        $name_array = [];
        if (! empty($this->prefix)) {
            $name_array[] = $this->prefix;
        }
        if (! empty($this->first_name)) {
            $name_array[] = $this->first_name;
        }
        if (! empty($this->middle_name)) {
            $name_array[] = $this->middle_name;
        }
        if (! empty($this->last_name)) {
            $name_array[] = $this->last_name;
        }

        $full_name = implode(' ', $name_array);
        $business_name = ! empty($this->supplier_business_name) ? $this->supplier_business_name.', ' : '';

        return $business_name.$full_name;
    }

    public function getContactAddressArrayAttribute()
    {
        $address_array = [];
        if (! empty($this->address_line_1)) {
            $address_array[] = $this->address_line_1;
        }
        if (! empty($this->address_line_2)) {
            $address_array[] = $this->address_line_2;
        }
        if (! empty($this->city)) {
            $address_array[] = $this->city;
        }
        if (! empty($this->state)) {
            $address_array[] = $this->state;
        }
        if (! empty($this->country)) {
            $address_array[] = $this->country;
        }
        if (! empty($this->zip_code)) {
            $address_array[] = $this->zip_code;
        }

        return $address_array;
    }

    /**
     * All user who have access to this contact
     * Applied only when selected_contacts is true for a user in
     * users table
     */
    public function userHavingAccess()
    {
        return $this->belongsToMany(\App\User::class, 'user_contact_access');
    }
}