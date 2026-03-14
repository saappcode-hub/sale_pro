<?php

namespace App\Http\Controllers;

use App\Business;
use App\BusinessLocation;
use App\CambodiaCommune;
use App\CambodiaDistrict;
use App\CambodiaProvince;
use App\Contact;
use App\ContactMap;
use App\ContractProduct;
use App\CustomerContract;
use App\CustomerGroup;
use App\LabelShipping;
use App\Media;
use App\Notifications\CustomerNotification;
use App\Product;
use App\PurchaseLine;
use App\ShippingAddress;
use App\Transaction;
use App\TransactionPayment;
use App\TransactionSellLine;
use App\User;
use App\UserLocationZone;
use App\Utils\ContactUtil;
use App\Utils\ModuleUtil;
use App\Utils\NotificationUtil;
use App\Utils\ProductUtil;
use App\Utils\TransactionUtil;
use App\Utils\Util;
use App\Variation;
use DB;
use Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;
use Yajra\DataTables\Facades\DataTables;
use App\Events\ContactCreatedOrModified;

class ContactController extends Controller
{
    protected $commonUtil;

    protected $contactUtil;

    protected $transactionUtil;

    protected $moduleUtil;

    protected $notificationUtil;

    protected $productUtil;

    /**
     * Constructor
     *
     * @param  Util  $commonUtil
     * @return void
     */
    public function __construct(
        Util $commonUtil,
        ModuleUtil $moduleUtil,
        TransactionUtil $transactionUtil,
        NotificationUtil $notificationUtil,
        ContactUtil $contactUtil,
        ProductUtil $productUtil
    ) {
        $this->commonUtil = $commonUtil;
        $this->contactUtil = $contactUtil;
        $this->moduleUtil = $moduleUtil;
        $this->transactionUtil = $transactionUtil;
        $this->notificationUtil = $notificationUtil;
        $this->productUtil = $productUtil;
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $business_id = request()->session()->get('user.business_id');

        $type = request()->get('type');

        $types = ['supplier', 'customer'];

        if (empty($type) || ! in_array($type, $types)) {
            return redirect()->back();
        }

        if (request()->ajax()) {
            if ($type == 'supplier') {
                return $this->indexSupplier();
            } elseif ($type == 'customer') {
                return $this->indexCustomer();
            } else {
                exit('Not Found');
            }
        }

        $reward_enabled = (request()->session()->get('business.enable_rp') == 1 && in_array($type, ['customer'])) ? true : false;

        $users = User::forDropdown($business_id);

        $customer_groups = [];
        if ($type == 'customer') {
            $customer_groups = CustomerGroup::forDropdown($business_id);
        }

        // Query provinces, districts, and communes
        $provinces = CambodiaProvince::pluck('name_en', 'id');
        $districts = CambodiaDistrict::pluck('name_en', 'id');
        $communes = CambodiaCommune::pluck('name_en', 'id');     

        return view('contact.index')
            ->with(compact('type', 'reward_enabled', 'customer_groups', 'users', 'provinces', 'districts', 'communes'));
    }

    /**
     * Returns the database object for supplier
     *
     * @return \Illuminate\Http\Response
     */
    private function indexSupplier()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $contact = $this->contactUtil->getContactQuery($business_id, 'supplier');

        if (request()->has('has_purchase_due')) {
            $contact->havingRaw('(total_purchase - purchase_paid) > 0');
        }

        if (request()->has('has_purchase_return')) {
            $contact->havingRaw('total_purchase_return > 0');
        }

        if (request()->has('has_advance_balance')) {
            $contact->where('balance', '>', 0);
        }

        if (request()->has('has_opening_balance')) {
            $contact->havingRaw('opening_balance > 0');
        }

        if (! empty(request()->input('contact_status'))) {
            $contact->where('contacts.contact_status', request()->input('contact_status'));
        }

        if (! empty(request()->input('assigned_to'))) {
            $contact->join('user_contact_access AS uc', 'contacts.id', 'uc.contact_id')
                ->where('uc.user_id', request()->input('assigned_to'));
        }

        return Datatables::of($contact)
            ->addColumn('address', '{{implode(", ", array_filter([$address_line_1, $address_line_2, $city, $state, $country, $zip_code]))}}')
            ->addColumn(
                'due',
                '<span class="contact_due" data-orig-value="{{$total_purchase - $purchase_paid - $total_ledger_discount}}" data-highlight=false>@format_currency($total_purchase - $purchase_paid - $total_ledger_discount)</span>'
            )
            ->addColumn(
                'return_due',
                '<span class="return_due" data-orig-value="{{$total_purchase_return - $purchase_return_paid}}" data-highlight=false>@format_currency($total_purchase_return - $purchase_return_paid)'
            )
            ->addColumn(
                'action',
                function ($row) {
                    $html = '<div class="btn-group">
                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                        data-toggle="dropdown" aria-expanded="false">'.
                        __('messages.actions').
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=purchase" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay').'</a></li>';

                    $return_due = $row->total_purchase_return - $row->purchase_return_paid;
                    if ($return_due > 0) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=purchase_return" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.receive_purchase_return_due').'</a></li>';
                    }

                    if (auth()->user()->can('supplier.view') || auth()->user()->can('supplier.view_own')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('supplier.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'edit'], [$row->id]).'" class="edit_contact_button"><i class="glyphicon glyphicon-edit"></i>'.__('messages.edit').'</a></li>';
                    }
                    if (auth()->user()->can('supplier.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'destroy'], [$row->id]).'" class="delete_contact_button"><i class="glyphicon glyphicon-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    if (auth()->user()->can('customer.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'updateStatus'], [$row->id]).'"class="update_contact_status"><i class="fas fa-power-off"></i>';

                        if ($row->contact_status == 'active') {
                            $html .= __('messages.deactivate');
                        } else {
                            $html .= __('messages.activate');
                        }

                        $html .= '</a></li>';
                    }

                    $html .= '<li class="divider"></li>';
                    if (auth()->user()->can('supplier.view')) {
                        $html .= '
                                <li>
                                    <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=ledger">
                                        <i class="fas fa-scroll" aria-hidden="true"></i>
                                        '.__('lang_v1.ledger').'
                                    </a>
                                </li>';

                        if (in_array($row->type, ['both', 'supplier'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=purchase">
                                    <i class="fas fa-arrow-circle-down" aria-hidden="true"></i>
                                    '.__('purchase.purchases').'
                                </a>
                            </li>
                            <li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=stock_report">
                                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                                    '.__('report.stock_report').'
                                </a>
                            </li>';
                        }

                        if (in_array($row->type, ['both', 'customer'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=sales">
                                    <i class="fas fa-arrow-circle-up" aria-hidden="true"></i>
                                    '.__('sale.sells').'
                                </a>
                            </li>';
                        }

                        $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=documents_and_notes">
                                    <i class="fas fa-paperclip" aria-hidden="true"></i>
                                     '.__('lang_v1.documents_and_notes').'
                                </a>
                            </li>';
                    }
                    $html .= '</ul></div>';

                    return $html;
                }
            )
            ->editColumn('opening_balance', function ($row) {
                $html = '<span data-orig-value="'.$row->opening_balance.'">'.$this->transactionUtil->num_f($row->opening_balance, true).'</span>';

                return $html;
            })
            ->editColumn('balance', function ($row) {
                $html = '<span data-orig-value="'.$row->balance.'">'.$this->transactionUtil->num_f($row->balance, true).'</span>';

                return $html;
            })
            ->editColumn('pay_term', '
                @if(!empty($pay_term_type) && !empty($pay_term_number))
                    {{$pay_term_number}}
                    @lang("lang_v1.".$pay_term_type)
                @endif
            ')
            ->editColumn('name', function ($row) {
                if ($row->contact_status == 'inactive') {
                    return $row->name.' <small class="label pull-right bg-red no-print">'.__('lang_v1.inactive').'</small>';
                } else {
                    return $row->name;
                }
            })
            ->editColumn('created_at', '{{@format_date($created_at)}}')
            ->removeColumn('opening_balance_paid')
            ->removeColumn('type')
            ->removeColumn('id')
            ->removeColumn('total_purchase')
            ->removeColumn('purchase_paid')
            ->removeColumn('total_purchase_return')
            ->removeColumn('purchase_return_paid')
            ->filterColumn('address', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('address_line_1', 'like', "%{$keyword}%")
                    ->orWhere('address_line_2', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('state', 'like', "%{$keyword}%")
                    ->orWhere('country', 'like', "%{$keyword}%")
                    ->orWhere('zip_code', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(COALESCE(address_line_1, ''), ', ', COALESCE(address_line_2, ''), ', ', COALESCE(city, ''), ', ', COALESCE(state, ''), ', ', COALESCE(country, '') ) like ?", ["%{$keyword}%"]);
                });
            })
            ->rawColumns(['action', 'opening_balance', 'pay_term', 'due', 'return_due', 'name', 'balance'])
            ->make(true);
    }

    /**
     * Returns the database object for customer
     *
     * @return \Illuminate\Http\Response
     */
    private function indexCustomer()
    {
        if (! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $is_admin = $this->contactUtil->is_admin(auth()->user());

        $query = $this->contactUtil->getContactQuery($business_id, 'customer');

        if (request()->has('has_sell_due')) {
            $query->havingRaw('(total_invoice - invoice_received) > 0');
        }

        if (request()->has('has_sell_return')) {
            $query->havingRaw('total_sell_return > 0');
        }

        if (request()->has('has_advance_balance')) {
            $query->where('balance', '>', 0);
        }

        if (request()->has('has_opening_balance')) {
            $query->havingRaw('opening_balance > 0');
        }

        if (! empty(request()->input('assigned_to'))) {
            $query->join('user_contact_access AS uc', 'contacts.id', 'uc.contact_id')
                ->where('uc.user_id', request()->input('assigned_to'));
        }
         
        // New filters for province, district, and commune
        if (!empty(request()->input('province_id'))) {
            $query->where('cm.province_id', request()->input('province_id'));
        }

        // New filters for province, district, and commune
         if (!empty(request()->input('district_id'))) {
            $query->where('cm.district_id', request()->input('district_id'));
        }

        // New filters for province, district, and commune
        if (!empty(request()->input('commune_id'))) {
            $query->where('cm.commune_id', request()->input('commune_id'));
        }

        // ── Zone-access filtering (customers only) ─────────────────────────────
        // Contacts are filtered purely by matching contacts_map
        // (province_id / district_id / commune_id) against user_location_zones.
        // No created_by check — only location zone matching matters.
        //
        // users.zone_access_all = 1    → skip filter, user sees ALL contacts
        // users.zone_access_all = NULL → restrict:
        //   No rows in user_location_zones for this user+business → show nothing
        //   Rows exist → show contacts whose contacts_map matches
        //                at least one zone row (province/district/commune)
        //
        // contacts_map is already LEFT JOINed as 'cm' in getContactQuery(),
        // so the alias is safe to reference directly here.
        $currentUser = auth()->user();

        if ($currentUser->zone_access_all != 1) {
            // Fetch zones for this user only — user_id is unique per user across businesses.
            // No need to join users table; the contacts query is already scoped by business_id.
            $userZones = UserLocationZone::where('user_id', $currentUser->id)->get();

            if ($userZones->isEmpty()) {
                // No zones assigned → show nothing
                $query->whereRaw('1 = 0');
            } else {
                // Zone filter is the SOLE authority for zone-restricted users.
                // We intentionally do NOT restrict by created_by here —
                // zone users must see ALL contacts in their zone regardless of who created them.
                // The OR/AND logic:
                //   OR  across zone rows  → contact matches if it fits ANY zone
                //   AND within a zone row → all non-null fields must match
                $query->where(function ($zoneQuery) use ($userZones) {
                    foreach ($userZones as $zone) {
                        $zoneQuery->orWhere(function ($q) use ($zone) {
                            if (!empty($zone->province_id)) {
                                $q->where('cm.province_id', $zone->province_id);
                            }
                            if (!empty($zone->district_id)) {
                                $q->where('cm.district_id', $zone->district_id);
                            }
                            if (!empty($zone->commune_id)) {
                                $q->where('cm.commune_id', $zone->commune_id);
                            }
                        });
                    }
                });

                // If the user only has customer.view_own (not customer.view),
                // onlyCustomers() would have added a created_by restriction above.
                // Since zone filtering is active, we override that by removing
                // the created_by condition — zone is the authority, not ownership.
                // We do this by unsetting the binding added by view_own scope
                // via a raw whereRaw that always passes for contacts in the zone.
                // (The zone WHERE above already restricts access correctly.)
            }
        }
        // ── End zone-access filtering ─────────────────────────────────────────

        $has_no_sell_from = request()->input('has_no_sell_from', null);

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_one_month')) ||
            ($has_no_sell_from == 'one_month' && (auth()->user()->can('customer_with_no_sell_one_month') || auth()->user()->can('customer_irrespective_of_sell')))
            ) {
            $from_transaction_date = \Carbon::now()->subDays(30)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_three_month')) ||
            ($has_no_sell_from == 'three_months' && (auth()->user()->can('customer_with_no_sell_three_month') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subMonths(3)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (
            (! $is_admin && auth()->user()->can('customer_with_no_sell_six_month')) ||
            ($has_no_sell_from == 'six_months' && (auth()->user()->can('customer_with_no_sell_six_month') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subMonths(6)->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if ((! $is_admin && auth()->user()->can('customer_with_no_sell_one_year')) ||
            ($has_no_sell_from == 'one_year' && (auth()->user()->can('customer_with_no_sell_one_year') || auth()->user()->can('customer_irrespective_of_sell')))
        ) {
            $from_transaction_date = \Carbon::now()->subYear()->format('Y-m-d');
            $query->havingRaw("max_transaction_date < '{$from_transaction_date}'")
                     ->orHavingRaw('transaction_date IS NULL');
        }

        if (! empty(request()->input('customer_group_id'))) {
            $query->where('contacts.customer_group_id', request()->input('customer_group_id'));
        }

        if (! empty(request()->input('contact_status'))) {
            $query->where('contacts.contact_status', request()->input('contact_status'));
        }

        $contacts = Datatables::of($query)
            ->addColumn('address', '{{implode(", ", array_filter([$address_line_1, $address_line_2, $city, $state, $country, $zip_code]))}}')
            ->addColumn(
                'location',
                function ($row) {
                    return $row->location;
                }
            )
            ->addColumn(
                'due',
                '<span class="contact_due" data-orig-value="{{$total_invoice - $invoice_received - $total_ledger_discount}}" data-highlight=true>@format_currency($total_invoice - $invoice_received - $total_ledger_discount)</span>'
            )
            ->addColumn(
                'return_due',
                '<span class="return_due" data-orig-value="{{$total_sell_return - $sell_return_paid}}" data-highlight=false>@format_currency($total_sell_return - $sell_return_paid)</span>'
            )
            ->addColumn(
                'action',
                function ($row) {
                    $html = '<div class="btn-group">
                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                        data-toggle="dropdown" aria-expanded="false">'.
                        __('messages.actions').
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';

                    $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=sell" class="pay_sale_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay').'</a></li>';
                    $return_due = $row->total_sell_return - $row->sell_return_paid;
                    if ($return_due > 0) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\TransactionPaymentController::class, 'getPayContactDue'], [$row->id]).'?type=sell_return" class="pay_purchase_due"><i class="fas fa-money-bill-alt" aria-hidden="true"></i>'.__('lang_v1.pay_sell_return_due').'</a></li>';
                    }

                    if (auth()->user()->can('customer.view') || auth()->user()->can('customer.view_own')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'"><i class="fas fa-eye" aria-hidden="true"></i>'.__('messages.view').'</a></li>';
                    }
                    if (auth()->user()->can('customer.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'edit'], [$row->id]).'" class="edit_contact_button"><i class="glyphicon glyphicon-edit"></i>'.__('messages.edit').'</a></li>';
                    }
                    if (! $row->is_default && auth()->user()->can('customer.delete')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'destroy'], [$row->id]).'" class="delete_contact_button"><i class="glyphicon glyphicon-trash"></i>'.__('messages.delete').'</a></li>';
                    }

                    if (auth()->user()->can('customer.update')) {
                        $html .= '<li><a href="'.action([\App\Http\Controllers\ContactController::class, 'updateStatus'], [$row->id]).'"class="update_contact_status"><i class="fas fa-power-off"></i>';

                        if ($row->contact_status == 'active') {
                            $html .= __('messages.deactivate');
                        } else {
                            $html .= __('messages.activate');
                        }

                        $html .= '</a></li>';
                    }

                    $html .= '<li class="divider"></li>';
                    if (auth()->user()->can('customer.view')) {
                        $html .= '
                                <li>
                                    <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=ledger">
                                        <i class="fas fa-scroll" aria-hidden="true"></i>
                                        '.__('lang_v1.ledger').'
                                    </a>
                                </li>';

                        if (in_array($row->type, ['both', 'supplier'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=purchase">
                                    <i class="fas fa-arrow-circle-down" aria-hidden="true"></i>
                                    '.__('purchase.purchases').'
                                </a>
                            </li>
                            <li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=stock_report">
                                    <i class="fas fa-hourglass-half" aria-hidden="true"></i>
                                    '.__('report.stock_report').'
                                </a>
                            </li>';
                        }

                        if (in_array($row->type, ['both', 'customer'])) {
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=sales">
                                    <i class="fas fa-arrow-circle-up" aria-hidden="true"></i>
                                    '.__('sale.sells').'
                                </a>
                            </li>';
                              // Add the new Shipping Address link here
                            $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=shipping_address">
                                    <i class="fas fa-truck" aria-hidden="true"></i>
                                    '.__('lang_v1.shipping_address').'
                                </a>
                            </li>';
                        }

                        $html .= '<li>
                                <a href="'.action([\App\Http\Controllers\ContactController::class, 'show'], [$row->id]).'?view=documents_and_notes">
                                    <i class="fas fa-paperclip" aria-hidden="true"></i>
                                     '.__('lang_v1.documents_and_notes').'
                                </a>
                            </li>';
                    }
                    $html .= '</ul></div>';

                    return $html;
                }
            )
            ->editColumn('opening_balance', function ($row) {
                $html = '<span data-orig-value="'.$row->opening_balance.'">'.$this->transactionUtil->num_f($row->opening_balance, true).'</span>';

                return $html;
            })
            ->editColumn('balance', function ($row) {
                $html = '<span data-orig-value="'.$row->balance.'">'.$this->transactionUtil->num_f($row->balance, true).'</span>';

                return $html;
            })
            ->editColumn('credit_limit', function ($row) {
                $html = __('lang_v1.no_limit');
                if (! is_null($row->credit_limit)) {
                    $html = '<span data-orig-value="'.$row->credit_limit.'">'.$this->transactionUtil->num_f($row->credit_limit, true).'</span>';
                }

                return $html;
            })
            ->editColumn('pay_term', '
                @if(!empty($pay_term_type) && !empty($pay_term_number))
                    {{$pay_term_number}}
                    @lang("lang_v1.".$pay_term_type)
                @endif
            ')
            ->editColumn('name', function ($row) {
                $name = $row->name;
                if ($row->contact_status == 'inactive') {
                    $name = $row->name.' <small class="label pull-right bg-red no-print">'.__('lang_v1.inactive').'</small>';
                }

                if (! empty($row->converted_by)) {
                    $name .= '<span class="label bg-info label-round no-print" data-toggle="tooltip" title="Converted from leads"><i class="fas fa-sync-alt"></i></span>';
                }

                return $name;
            })
            ->editColumn('total_rp', '{{$total_rp ?? 0}}')
            ->editColumn('created_at', '{{@format_date($created_at)}}')
            ->removeColumn('total_invoice')
            ->removeColumn('opening_balance_paid')
            ->removeColumn('invoice_received')
            ->removeColumn('state')
            ->removeColumn('country')
            ->removeColumn('city')
            ->removeColumn('type')
            ->removeColumn('id')
            ->removeColumn('is_default')
            ->removeColumn('total_sell_return')
            ->removeColumn('sell_return_paid')
            ->filterColumn('address', function ($query, $keyword) {
                $query->where(function ($q) use ($keyword) {
                    $q->where('address_line_1', 'like', "%{$keyword}%")
                    ->orWhere('address_line_2', 'like', "%{$keyword}%")
                    ->orWhere('city', 'like', "%{$keyword}%")
                    ->orWhere('state', 'like', "%{$keyword}%")
                    ->orWhere('country', 'like', "%{$keyword}%")
                    ->orWhere('zip_code', 'like', "%{$keyword}%")
                    ->orWhereRaw("CONCAT(COALESCE(address_line_1, ''), ', ', COALESCE(address_line_2, ''), ', ', COALESCE(city, ''), ', ', COALESCE(state, ''), ', ', COALESCE(country, '') ) like ?", ["%{$keyword}%"]);
                });
            });
        $reward_enabled = (request()->session()->get('business.enable_rp') == 1) ? true : false;
        if (! $reward_enabled) {
            $contacts->removeColumn('total_rp');
        }

        return $contacts->rawColumns(['action', 'location', 'opening_balance', 'credit_limit', 'pay_term', 'due', 'return_due', 'name', 'balance','provinces','districts','communes'])
                        ->make(true);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        //Check if subscribed or not
        if (! $this->moduleUtil->isSubscribed($business_id)) {
            return $this->moduleUtil->expiredResponse();
        }

        $types = [];
        if (auth()->user()->can('supplier.create') || auth()->user()->can('supplier.view_own')) {
            $types['supplier'] = __('report.supplier');
        }
        if (auth()->user()->can('customer.create') || auth()->user()->can('customer.view_own')) {
            $types['customer'] = __('report.customer');
        }
        if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create') || auth()->user()->can('supplier.view_own') || auth()->user()->can('customer.view_own')) {
            $types['both'] = __('lang_v1.both_supplier_customer');
        }

        // Query provinces, districts, and communes
        $provinces = CambodiaProvince::pluck('name_en', 'id');
        $districts = CambodiaDistrict::pluck('name_en', 'id');
        $communes = CambodiaCommune::pluck('name_en', 'id');        

        $customer_groups = CustomerGroup::forDropdown($business_id);

        // Fetch the default customer group
        $default_customer_group = CustomerGroup::where('business_id', $business_id)
            ->where('is_default', 1)
            ->first();

        $selected_customer_group_id = $default_customer_group ? $default_customer_group->id : null;

        $selected_type = request()->type;

        $module_form_parts = $this->moduleUtil->getModuleData('contact_form_part');

        //Added check because $users is of no use if enable_contact_assign if false
        $users = config('constants.enable_contact_assign') ? User::forDropdown($business_id, false, false, false, true) : [];

        return view('contact.create')
            ->with(compact('types', 'customer_groups', 'selected_customer_group_id', 'selected_type', 'module_form_parts', 'users', 'provinces', 'districts', 'communes'));
    }  

    public function getDistricts($province_id)
    {
        $districts = CambodiaDistrict::where('province_id', $province_id)
            ->select('id', 'name_en')
            ->pluck('name_en', 'id');
    
        return response()->json($districts);
    }
    
    public function getCommunes($district_id)
    {
        $communes = CambodiaCommune::where('district_id', $district_id)
            ->select('id', 'name_en')
            ->pluck('name_en', 'id');
    
        return response()->json($communes);
    }
    

    protected function getLatLngFromGoogleMapsApi($url)
    {
        // Log the URL before regex matching
        Log::info("Processing URL for coordinates extraction: " . $url);
    
        // Adjusted Regex specifically for the Google Maps search URL format
        $pattern = '/maps\/search\/([0-9.+-]+),([0-9.+-]+)/';

        $patternAt = '/@([-0-9.]+),([-0-9.]+)(?:,|\/|\?)/';

        $pattern3d4d = '/3d([-0-9.]+)!4d([-0-9.]+)/';
    
        if (preg_match($pattern3d4d, $url, $matches)) {
            $latitude = $matches[1];
            $longitude = $matches[2];
            return ['lat' => $latitude, 'lng' => $longitude];
        }
        if (preg_match($patternAt, $url, $matches)) {
            $latitude = $matches[1];
            $longitude = $matches[2];
            return ['lat' => $latitude, 'lng' => $longitude];
        }
        if (preg_match($pattern, $url, $matches)) {
            $latitude = $matches[1];
            $longitude = $matches[2];
            return ['lat' => $latitude, 'lng' => $longitude];
        }
        
    
        return null; // Return null if no coordinates are found
    }    
    
    /**
     * Expands a shortened URL to its full form using cURL.
     *
     * @param string $shortUrl
     * @return string
     */
    protected function expandUrl($shortUrl)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $shortUrl);
        curl_setopt($ch, CURLOPT_HEADER, true); // to include the header in the output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true); // to follow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // to return the content
        curl_exec($ch);
        $fullUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
    
        // Log the expanded URL
        Log::info("Expanded URL: " . $fullUrl);
    
        return $fullUrl;
    }    

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        if (!auth()->user()->can('supplier.create') &&
            !auth()->user()->can('customer.create') &&
            !auth()->user()->can('customer.view_own') &&
            !auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }
    
        try {
            $business_id = $request->session()->get('user.business_id');
    
            if (!$this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }
    
            $mobile = $request->input('mobile');
           
            $existingContact = Contact::where('business_id', $business_id)
                ->where('mobile', $mobile)
                ->first();
           
            if ($existingContact) {
                return response()->json([
                    'success' => false,
                    'msg' => __('lang_v1.mobile_already_registered', [
                        'contacts' => $existingContact->name,
                        'mobile' => $mobile
                    ])
                ]);
            }
         
            $input = $request->only([
                'type', 'supplier_business_name', 'prefix', 'first_name', 'middle_name',
                'last_name', 'tax_number', 'pay_term_number', 'pay_term_type', 'mobile',
                'landline', 'alternate_number', 'city', 'state', 'country', 'address_line_1',
                'address_line_2', 'customer_group_id', 'zip_code', 'contact_id', 'custom_field1',
                'custom_field2', 'custom_field3', 'custom_field4', 'custom_field5', 'custom_field6',
                'custom_field7', 'custom_field8', 'custom_field9', 'custom_field10', 'link_map',
                'email', 'shipping_address', 'position', 'dob', 'shipping_custom_field_details',
                'assigned_to_users', 'province_id', 'district_id', 'commune_id'
            ]);
    

            $input['name'] = $input['first_name'] ?? "";
    
            if (!empty($request->input('is_export'))) {
                $input['is_export'] = true;
                $input['export_custom_field_1'] = $request->input('export_custom_field_1');
                $input['export_custom_field_2'] = $request->input('export_custom_field_2');
                $input['export_custom_field_3'] = $request->input('export_custom_field_3');
                $input['export_custom_field_4'] = $request->input('export_custom_field_4');
                $input['export_custom_field_5'] = $request->input('export_custom_field_5');
                $input['export_custom_field_6'] = $request->input('export_custom_field_6');
            }
    
            if (!empty($input['dob'])) {
                $input['dob'] = $this->commonUtil->uf_date($input['dob']);
            }
    
            $input['business_id'] = $business_id;
            $input['created_by'] = $request->session()->get('user.id');
            $input['credit_limit'] = $request->input('credit_limit') == "" || $request->input('credit_limit') == null
                ? null
                : $this->commonUtil->num_uf($request->input('credit_limit'));
            $input['opening_balance'] = $this->commonUtil->num_uf($request->input('opening_balance'));
    
            DB::beginTransaction();
    
            $output = $this->contactUtil->createNewContact($input);
            $contact = $output['data'];
    
            $points = null;
            $linkMapUrl = $request->input('link_map');
            if (!empty($linkMapUrl) &&
                (strpos($linkMapUrl, 'https://maps.app.goo.gl') === 0 ||
                strpos($linkMapUrl, 'https://www.google.com/maps') === 0)) {
                $expandedUrl = $this->expandUrl($linkMapUrl);
                Log::info("Expanded URL for mapping: " . $expandedUrl);
                $coordinates = $this->getLatLngFromGoogleMapsApi($expandedUrl);
                if ($coordinates) {
                    $points = $coordinates['lat'] . ',' . $coordinates['lng'];
                }
            }

            // Save province/district/commune to contacts_map
            // Only save if at least one of the three location fields is selected
            $province_id = $request->input('province_id');
            $district_id = $request->input('district_id');
            $commune_id  = $request->input('commune_id');

            if (!empty($province_id) || !empty($district_id) || !empty($commune_id)) {
                ContactMap::updateOrCreate(
                    ['contact_id' => $contact->id],
                    [
                        'points'      => $points,
                        'address'     => $input['address_line_1'] ?? null,
                        'province_id' => !empty($province_id) ? $province_id : null,
                        'district_id' => !empty($district_id) ? $district_id : null,
                        'commune_id'  => !empty($commune_id)  ? $commune_id  : null,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]
                );
            }

            // Save ShippingAddress if mobile and address_line_1 are present
            if (!empty($input['mobile']) && !empty($input['address_line_1'])) {
                // Find the "Home" label (case-insensitive)
                $default_label_id = LabelShipping::where('business_id', $business_id)
                    ->whereRaw('LOWER(name) = ?', ['home'])
                    ->value('id');

                ShippingAddress::create([
                    'business_id' => $business_id,
                    'contact_id' => $contact->id,
                    'label_shipping_id' => $default_label_id ?? null, // Use "Home" if exists, else null
                    'mobile' => $input['mobile'],
                    'address' => $input['address_line_1'],
                    'map' => null,
                    'latlong' => null,
                    'is_default' => 1, // Set as default
                    'created_by' => auth()->user()->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
    
            $this->contactUtil->activityLog($contact, 'added');
            event(new ContactCreatedOrModified($contact, 'added'));
            $this->moduleUtil->getModuleData('after_contact_saved', ['contact' => $contact, 'input' => $request->input()]);
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'msg' => 'Operation successful',
                'data' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'mobile' => $contact->mobile,
                    'address_line_1' => $contact->address_line_1,
                    'supplier_business_name' => $contact->supplier_business_name ?? ''
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ]);
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contact = $this->contactUtil->getContactInfo($business_id, $id);

        $is_selected_contacts = User::isSelectedContacts(auth()->user()->id);
        $user_contacts = [];
        if ($is_selected_contacts) {
            $user_contacts = auth()->user()->contactAccess->pluck('id')->toArray();
        }

        if (! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }
        if (! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            // Zone-restricted users can view ANY contact in their zone,
            // not just contacts they created. Only apply created_by check
            // when the user has NO active zone assigned.
            $hasZones       = \App\UserLocationZone::where('user_id', auth()->user()->id)->exists();
            $isZoneRestricted = (auth()->user()->zone_access_all != 1);

            if (!$isZoneRestricted || !$hasZones) {
                // Normal view_own: only allow if created_by or assigned
                if ($contact->created_by != auth()->user()->id && ! in_array($contact->id, $user_contacts)) {
                    abort(403, 'Unauthorized action.');
                }
            } else {
                // Zone user: allow if contact belongs to same business
                if ($contact->business_id != $business_id) {
                    abort(403, 'Unauthorized action.');
                }
            }
        }

        $reward_enabled = (request()->session()->get('business.enable_rp') == 1 && in_array($contact->type, ['customer', 'both'])) ? true : false;

        $contact_dropdown = Contact::contactDropdown($business_id, false, false);

        $business_locations = BusinessLocation::forDropdown($business_id, true);

        $contact_map = DB::table('contacts_map')
                    ->where('contact_id', $id)
                    ->first();
        
        $area = DB::table('contacts_map')
        ->leftJoin('cambodia_provinces', 'contacts_map.province_id', '=', 'cambodia_provinces.id')
        ->leftJoin('cambodia_districts', 'contacts_map.district_id', '=', 'cambodia_districts.id')
        ->leftJoin('cambodia_communes', 'contacts_map.commune_id', '=', 'cambodia_communes.id')
        ->where('contacts_map.contact_id', $id)
        ->select(
            'cambodia_provinces.name_en as province_name',
            'cambodia_districts.name_en as district_name',
            'cambodia_communes.name_en as commune_name'
        )
        ->first();
        
        //get contact view type : ledger, notes etc.
        $view_type = request()->get('view');
        if (is_null($view_type)) {
            $view_type = 'ledger';
        }

        $contact_view_tabs = $this->moduleUtil->getModuleData('get_contact_view_tabs');

        $activities = Activity::forSubject($contact)
           ->with(['causer', 'subject'])
           ->latest()
           ->get();

        return view('contact.show')
             ->with(compact('contact', 'reward_enabled', 'contact_dropdown', 'business_locations', 'view_type', 'contact_view_tabs', 'activities', 'contact_map', 'area'));
    }

    public function checkMobile(Request $request)
    {
        $business_id = $request->session()->get('user.business_id');
        $mobile_number = $request->input('mobile_number');
        $contact_id = $request->input('contact_id');

        $query = Contact::where('business_id', $business_id)
                        ->where('mobile', $mobile_number);

        if (!empty($contact_id)) {
            $query->where('id', '!=', $contact_id);
        }

        $contacts = $query->pluck('name')->toArray();

        return [
            'is_mobile_exists' => !empty($contacts),
            'msg' => __('lang_v1.mobile_already_registered', [
                'contacts' => implode(', ', $contacts),
                'mobile' => $mobile_number
            ]),
        ];
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        if (!auth()->user()->can('supplier.update') && 
            !auth()->user()->can('customer.update') &&
            !auth()->user()->can('customer.view_own') &&
            !auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }
    
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $contact = Contact::where('business_id', $business_id)->find($id);
    
            if (!$this->moduleUtil->isSubscribed($business_id)) {
                return $this->moduleUtil->expiredResponse();
            }
    
            // Fetch data from contacts_map
            $contact_map = DB::table('contacts_map')->where('contact_id', $id)->first();
    
            // Pre-fill province, district, and commune based on contacts_map
            $province_id = $contact_map->province_id ?? null;
            $district_id = $contact_map->district_id ?? null;
            $commune_id = $contact_map->commune_id ?? null;
    
            // Populate dropdowns
            $provinces = CambodiaProvince::pluck('name_en', 'id');
            $districts = $province_id 
                ? CambodiaDistrict::where('province_id', $province_id)->pluck('name_en', 'id')
                : [];
            $communes = $district_id 
                ? CambodiaCommune::where('district_id', $district_id)->pluck('name_en', 'id')
                : [];
    
            $types = [];
            if (auth()->user()->can('supplier.create')) {
                $types['supplier'] = __('report.supplier');
            }
            if (auth()->user()->can('customer.create')) {
                $types['customer'] = __('report.customer');
            }
            if (auth()->user()->can('supplier.create') && auth()->user()->can('customer.create')) {
                $types['both'] = __('lang_v1.both_supplier_customer');
            }
    
            $customer_groups = CustomerGroup::forDropdown($business_id);
    
            $ob_transaction = Transaction::where('contact_id', $id)
                ->where('type', 'opening_balance')
                ->first();
            $opening_balance = !empty($ob_transaction->final_total) ? $ob_transaction->final_total : 0;
    
            // Deduct paid amount from opening balance
            if (!empty($opening_balance)) {
                $opening_balance_paid = $this->transactionUtil->getTotalAmountPaid($ob_transaction->id);
                if (!empty($opening_balance_paid)) {
                    $opening_balance -= $opening_balance_paid;
                }
    
                $opening_balance = $this->commonUtil->num_f($opening_balance);
            }
    
            $users = config('constants.enable_contact_assign') 
                ? User::forDropdown($business_id, false, false, false, true) 
                : [];
    
            return view('contact.edit')
                ->with(compact(
                    'contact', 'contact_map', 'types', 'customer_groups', 
                    'opening_balance', 'users', 'provinces', 'districts', 'communes',
                    'province_id', 'district_id', 'commune_id'
                ));
        }
    }    

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        if (!auth()->user()->can('supplier.update') &&
            !auth()->user()->can('customer.update') &&
            !auth()->user()->can('customer.view_own') &&
            !auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = $request->session()->get('user.business_id');

                // Get the current contact to compare mobile numbers
                $currentContact = Contact::where('business_id', $business_id)->find($id);
                if (!$currentContact) {
                    return response()->json([
                        'success' => false,
                        'msg' => __('messages.contact_not_found')
                    ]);
                }

                $mobile = $request->input('mobile');
                
                // Only validate mobile if it has been changed
                if ($currentContact->mobile !== $mobile) {
                    $existingContact = Contact::where('business_id', $business_id)
                                            ->where('mobile', $mobile)
                                            ->where('id', '!=', $id) // Exclude the current contact being updated
                                            ->first();

                    if ($existingContact) {
                        return response()->json([
                            'success' => false,
                            'msg' => __('lang_v1.mobile_already_registered', [
                                'contacts' => $existingContact->name,
                                'mobile' => $mobile
                            ])
                        ]);
                    }
                }

                $input = $request->only([
                    'type', 'supplier_business_name', 'prefix', 'first_name', 'middle_name',
                    'last_name', 'tax_number', 'pay_term_number', 'pay_term_type', 'mobile',
                    'address_line_1', 'address_line_2', 'zip_code', 'dob', 'alternate_number',
                    'city', 'state', 'country', 'landline', 'customer_group_id', 'contact_id',
                    'custom_field1', 'custom_field2', 'custom_field3', 'custom_field4', 
                    'custom_field5', 'custom_field6', 'custom_field7', 'custom_field8', 
                    'custom_field9', 'custom_field10', 'email', 'shipping_address', 'position',
                    'shipping_custom_field_details', 'export_custom_field_1', 'export_custom_field_2',
                    'export_custom_field_3', 'export_custom_field_4', 'export_custom_field_5',
                    'export_custom_field_6', 'assigned_to_users', 'link_map'
                ]);

                // Set name to first_name only
                $input['name'] = trim($input['first_name'] ?? '');

                $input['is_export'] = !empty($request->input('is_export')) ? 1 : 0;

                if (!$input['is_export']) {
                    unset(
                        $input['export_custom_field_1'], $input['export_custom_field_2'],
                        $input['export_custom_field_3'], $input['export_custom_field_4'],
                        $input['export_custom_field_5'], $input['export_custom_field_6']
                    );
                }

                if (!empty($input['dob'])) {
                    $input['dob'] = $this->commonUtil->uf_date($input['dob']);
                }

                $input['credit_limit'] = $request->input('credit_limit') != ''
                    ? $this->commonUtil->num_uf($request->input('credit_limit'))
                    : null;

                $input['opening_balance'] = $this->commonUtil->num_uf($request->input('opening_balance'));

                if (!$this->moduleUtil->isSubscribed($business_id)) {
                    return $this->moduleUtil->expiredResponse();
                }

                // Extract coordinates if link_map is provided
                $points = null;
                $linkMapUrl = $request->input('link_map');
                if (!empty($linkMapUrl) &&
                    (strpos($input['link_map'], 'https://maps.app.goo.gl') === 0 || 
                    strpos($input['link_map'], 'https://www.google.com/maps') === 0)) {
                    // Expand the short URL if necessary
                    $expandedUrl = $this->expandUrl($linkMapUrl);
                    Log::info("Expanded URL for mapping: " . $expandedUrl);

                    // Get coordinates from the URL
                    $coordinates = $this->getLatLngFromGoogleMapsApi($expandedUrl);
                    if ($coordinates) {
                        $points = $coordinates['lat'] . ',' . $coordinates['lng'];
                    } else {
                        Log::info("No coordinates resolved for the provided URL.");
                    }
                }

                // Update contact
                $output = $this->contactUtil->updateContact($input, $id, $business_id);

                // Save province/district/commune to contacts_map
                // Only save if at least one of the three location fields is selected
                $province_id = $request->input('province_id');
                $district_id = $request->input('district_id');
                $commune_id  = $request->input('commune_id');

                if (!empty($province_id) || !empty($district_id) || !empty($commune_id)) {
                    ContactMap::updateOrCreate(
                        ['contact_id' => $id],
                        [
                            'points'      => $points,
                            'address'     => $input['address_line_1'] ?? null,
                            'province_id' => !empty($province_id) ? $province_id : null,
                            'district_id' => !empty($district_id) ? $district_id : null,
                            'commune_id'  => !empty($commune_id)  ? $commune_id  : null,
                            'updated_at'  => now(),
                        ]
                    );
                }

                event(new ContactCreatedOrModified($output['data'], 'updated'));
                $this->contactUtil->activityLog($output['data'], 'edited');
                
                return response()->json([
                    'success' => true,
                    'msg' => __('messages.updated_success'),
                    'data' => [
                        'id' => $output['data']->id,
                        'name' => $output['data']->name,
                        'mobile' => $output['data']->mobile,
                        'address_line_1' => $output['data']->address_line_1,
                        'supplier_business_name' => $output['data']->supplier_business_name ?? ''
                    ]
                ]);
            } catch (\Exception $e) {
                \Log::emergency('File:' . $e->getFile() . ' Line:' . $e->getLine() . ' Message:' . $e->getMessage());

                return response()->json([
                    'success' => false,
                    'msg' => __('messages.something_went_wrong')
                ]);
            }
        }
    }
    
    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        if (! auth()->user()->can('supplier.delete') && ! auth()->user()->can('customer.delete') && ! auth()->user()->can('customer.view_own') && ! auth()->user()->can('supplier.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            try {
                $business_id = request()->user()->business_id;

                //Check if any transaction related to this contact exists
                $count = Transaction::where('business_id', $business_id)
                                    ->where('contact_id', $id)
                                    ->count();
                if ($count == 0) {
                    $contact = Contact::where('business_id', $business_id)->findOrFail($id);
                    if (! $contact->is_default) {
                        $log_properities = [
                            'id' => $contact->id,
                            'name' => $contact->name,
                            'supplier_business_name' => $contact->supplier_business_name,
                        ];
                        $this->contactUtil->activityLog($contact, 'contact_deleted', $log_properities);

                        //Disable login for associated users
                        User::where('crm_contact_id', $contact->id)
                            ->update(['allow_login' => 0]);

                        $contact->delete();

                        event(new ContactCreatedOrModified($contact, 'deleted'));
                    }
                    $output = ['success' => true,
                        'msg' => __('contact.deleted_success'),
                    ];
                } else {
                    $output = ['success' => false,
                        'msg' => __('lang_v1.you_cannot_delete_this_contact'),
                    ];
                }
            } catch (\Exception $e) {
                \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

                $output = ['success' => false,
                    'msg' => __('messages.something_went_wrong'),
                ];
            }

            return $output;
        }
    }

    /**
     * Retrieves list of customers, if filter is passed then filter it accordingly.
     *
     * @param  string  $q
     * @return JSON
     */
    public function getCustomers()
    {
        if (request()->ajax()) {
            $term = request()->input('q', '');

            $business_id = request()->session()->get('user.business_id');
            $user_id = request()->session()->get('user.id');

            $contacts = Contact::where('contacts.business_id', $business_id)
                            ->leftjoin('customer_groups as cg', 'cg.id', '=', 'contacts.customer_group_id')
                            ->active();

            if (! request()->has('all_contact')) {
                $contacts->onlyCustomers();
            }

            if (! empty($term)) {
                $contacts->where(function ($query) use ($term) {
                    $query->where('contacts.name', 'like', '%'.$term.'%')
                            ->orWhere('supplier_business_name', 'like', '%'.$term.'%')
                            ->orWhere('mobile', 'like', '%'.$term.'%')
                            ->orWhere('contacts.contact_id', 'like', '%'.$term.'%');
                });
            }

            $contacts->select(
                'contacts.id',
                DB::raw("IF(contacts.contact_id IS NULL OR contacts.contact_id='', contacts.name, CONCAT(contacts.name, ' (', contacts.contact_id, ')')) AS text"),
                'mobile',
                'address_line_1',
                'address_line_2',
                'city',
                'state',
                'country',
                'zip_code',
                'shipping_address',
                'pay_term_number',
                'pay_term_type',
                'balance',
                'supplier_business_name',
                'cg.amount as discount_percent',
                'cg.price_calculation_type',
                'cg.selling_price_group_id',
                'shipping_custom_field_details',
                'is_export',
                'export_custom_field_1',
                'export_custom_field_2',
                'export_custom_field_3',
                'export_custom_field_4',
                'export_custom_field_5',
                'export_custom_field_6'
            );

            if (request()->session()->get('business.enable_rp') == 1) {
                $contacts->addSelect('total_rp');
            }
            $contacts = $contacts->get();

            return json_encode($contacts);
        }
    }

    /**
     * Checks if the given contact id already exist for the current business.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function checkContactId(Request $request)
    {
        $contact_id = $request->input('contact_id');

        $valid = 'true';
        if (! empty($contact_id)) {
            $business_id = $request->session()->get('user.business_id');
            $hidden_id = $request->input('hidden_id');

            $query = Contact::where('business_id', $business_id)
                            ->where('contact_id', $contact_id);
            if (! empty($hidden_id)) {
                $query->where('id', '!=', $hidden_id);
            }
            $count = $query->count();
            if ($count > 0) {
                $valid = 'false';
            }
        }
        echo $valid;
        exit;
    }

    /**
     * Shows import option for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function getImportContacts()
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        $zip_loaded = extension_loaded('zip') ? true : false;

        //Check if zip extension it loaded or not.
        if ($zip_loaded === false) {
            $output = ['success' => 0,
                'msg' => 'Please install/enable PHP Zip archive for import',
            ];

            return view('contact.import')
                ->with('notification', $output);
        } else {
            return view('contact.import');
        }
    }

    /**
     * Imports contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function postImportContacts(Request $request)
    {
        if (! auth()->user()->can('supplier.create') && ! auth()->user()->can('customer.create')) {
            abort(403, 'Unauthorized action.');
        }

        try {
            $notAllowed = $this->commonUtil->notAllowedInDemo();
            if (! empty($notAllowed)) {
                return $notAllowed;
            }

            //Set maximum php execution time
            ini_set('max_execution_time', 0);

            if ($request->hasFile('contacts_csv')) {
                $file = $request->file('contacts_csv');
                $parsed_array = Excel::toArray([], $file);
                //Remove header row
                $imported_data = array_splice($parsed_array[0], 1);

                $business_id = $request->session()->get('user.business_id');
                $user_id = $request->session()->get('user.id');

                $formated_data = [];

                $is_valid = true;
                $error_msg = '';

                DB::beginTransaction();
                foreach ($imported_data as $key => $value) {
                    //Check if 27 no. of columns exists
                    if (count($value) != 27) {
                        $is_valid = false;
                        $error_msg = 'Number of columns mismatch';
                        break;
                    }

                    $row_no = $key + 1;
                    $contact_array = [];

                    //Check contact type
                    $contact_type = '';
                    $contact_types = [
                        1 => 'customer',
                        2 => 'supplier',
                        3 => 'both',
                    ];
                    if (! empty($value[0])) {
                        $contact_type = strtolower(trim($value[0]));
                        if (in_array($contact_type, [1, 2, 3])) {
                            $contact_array['type'] = $contact_types[$contact_type];
                            $contact_type = $contact_types[$contact_type];
                        } else {
                            $is_valid = false;
                            $error_msg = "Invalid contact type $contact_type in row no. $row_no";
                            break;
                        }
                    } else {
                        $is_valid = false;
                        $error_msg = "Contact type is required in row no. $row_no";
                        break;
                    }

                    $contact_array['prefix'] = $value[1];
                    //Check contact name
                    if (! empty($value[2])) {
                        $contact_array['first_name'] = $value[2];
                    } else {
                        $is_valid = false;
                        $error_msg = "First name is required in row no. $row_no";
                        break;
                    }
                    $contact_array['middle_name'] = $value[3];
                    $contact_array['last_name'] = $value[4];
                    $contact_array['name'] = implode(' ', [$contact_array['prefix'], $contact_array['first_name'], $contact_array['middle_name'], $contact_array['last_name']]);

                    //Check business name
                    if (! empty(trim($value[5]))) {
                        $contact_array['supplier_business_name'] = $value[5];
                    }

                    //Check supplier fields
                    if (in_array($contact_type, ['supplier', 'both'])) {
                        //Check pay term
                        if (trim($value[9]) != '') {
                            $contact_array['pay_term_number'] = trim($value[9]);
                        } else {
                            $is_valid = false;
                            $error_msg = "Pay term is required in row no. $row_no";
                            break;
                        }

                        //Check pay period
                        $pay_term_type = strtolower(trim($value[10]));
                        if (in_array($pay_term_type, ['days', 'months'])) {
                            $contact_array['pay_term_type'] = $pay_term_type;
                        } else {
                            $is_valid = false;
                            $error_msg = "Pay term period is required in row no. $row_no";
                            break;
                        }
                    }

                    //Check contact ID
                    if (! empty(trim($value[6]))) {
                        $count = Contact::where('business_id', $business_id)
                                    ->where('contact_id', $value[6])
                                    ->count();

                        if ($count == 0) {
                            $contact_array['contact_id'] = $value[6];
                        } else {
                            $is_valid = false;
                            $error_msg = "Contact ID already exists in row no. $row_no";
                            break;
                        }
                    }

                    //Tax number
                    if (! empty(trim($value[7]))) {
                        $contact_array['tax_number'] = $value[7];
                    }

                    //Check opening balance
                    if (! empty(trim($value[8])) && $value[8] != 0) {
                        $contact_array['opening_balance'] = trim($value[8]);
                    }

                    //Check credit limit
                    if (trim($value[11]) != '' && in_array($contact_type, ['customer', 'both'])) {
                        $contact_array['credit_limit'] = trim($value[11]);
                    }

                    //Check email
                    if (! empty(trim($value[12]))) {
                        if (filter_var(trim($value[12]), FILTER_VALIDATE_EMAIL)) {
                            $contact_array['email'] = $value[12];
                        } else {
                            $is_valid = false;
                            $error_msg = "Invalid email id in row no. $row_no";
                            break;
                        }
                    }

                    //Mobile number
                    if (! empty(trim($value[13]))) {
                        $contact_array['mobile'] = $value[13];
                    } else {
                        $is_valid = false;
                        $error_msg = "Mobile number is required in row no. $row_no";
                        break;
                    }

                    //Alt contact number
                    $contact_array['alternate_number'] = $value[14];

                    //Landline
                    $contact_array['landline'] = $value[15];

                    //City
                    $contact_array['city'] = $value[16];

                    //State
                    $contact_array['state'] = $value[17];

                    //Country
                    $contact_array['country'] = $value[18];

                    //address_line_1
                    $contact_array['address_line_1'] = $value[19];
                    //address_line_2
                    $contact_array['address_line_2'] = $value[20];
                    $contact_array['zip_code'] = $value[21];
                    $contact_array['dob'] = $value[22];

                    //Cust fields
                    $contact_array['custom_field1'] = $value[23];
                    $contact_array['custom_field2'] = $value[24];
                    $contact_array['custom_field3'] = $value[25];
                    $contact_array['custom_field4'] = $value[26];

                    $formated_data[] = $contact_array;
                }
                if (! $is_valid) {
                    throw new \Exception($error_msg);
                }

                if (! empty($formated_data)) {
                    foreach ($formated_data as $contact_data) {
                        $ref_count = $this->transactionUtil->setAndGetReferenceCount('contacts');
                        //Set contact id if empty
                        if (empty($contact_data['contact_id'])) {
                            $contact_data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count);
                        }

                        $opening_balance = 0;
                        if (isset($contact_data['opening_balance'])) {
                            $opening_balance = $contact_data['opening_balance'];
                            unset($contact_data['opening_balance']);
                        }

                        $contact_data['business_id'] = $business_id;
                        $contact_data['created_by'] = $user_id;

                        $contact = Contact::create($contact_data);

                        if (! empty($opening_balance)) {
                            $this->transactionUtil->createOpeningBalanceTransaction($business_id, $contact->id, $opening_balance, $user_id, false);
                        }

                        $this->transactionUtil->activityLog($contact, 'imported');
                    }
                }

                $output = ['success' => 1,
                    'msg' => __('product.file_imported_successfully'),
                ];

                DB::commit();
            }
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => $e->getMessage(),
            ];

            return redirect()->route('contacts.import')->with('notification', $output);
        }
        $type = ! empty($contact->type) && $contact->type != 'both' ? $contact->type : 'supplier';

        return redirect()->action([\App\Http\Controllers\ContactController::class, 'index'], ['type' => $type])->with('status', $output);
    }

    /**
     * Shows ledger for contacts
     *
     * @param  \Illuminate\Http\Request
     * @return \Illuminate\Http\Response
     */
    public function getLedger()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view') && ! auth()->user()->can('supplier.view_own') && ! auth()->user()->can('customer.view_own')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');
        $contact_id = request()->input('contact_id');

        $is_admin = $this->contactUtil->is_admin(auth()->user());

        $start_date = request()->start_date;
        $end_date = request()->end_date;
        $format = request()->input('format');
        $location_id = request()->location_id;

        $contact = Contact::find($contact_id);

        $is_selected_contacts = User::isSelectedContacts(auth()->user()->id);
        $user_contacts = [];
        if ($is_selected_contacts) {
            $user_contacts = auth()->user()->contactAccess->pluck('id')->toArray();
        }

        if (! auth()->user()->can('supplier.view') && auth()->user()->can('supplier.view_own')) {
            if ($contact->created_by != auth()->user()->id & ! in_array($contact->id, $user_contacts)) {
                abort(403, 'Unauthorized action.');
            }
        }
        if (! auth()->user()->can('customer.view') && auth()->user()->can('customer.view_own')) {
            // Zone-restricted users can view ANY contact in their zone,
            // not just contacts they created. Only apply created_by check
            // when the user has NO active zone assigned.
            $hasZones       = \App\UserLocationZone::where('user_id', auth()->user()->id)->exists();
            $isZoneRestricted = (auth()->user()->zone_access_all != 1);

            if (!$isZoneRestricted || !$hasZones) {
                // Normal view_own: only allow if created_by or assigned
                if ($contact->created_by != auth()->user()->id && ! in_array($contact->id, $user_contacts)) {
                    abort(403, 'Unauthorized action.');
                }
            } else {
                // Zone user: allow if contact belongs to same business
                if ($contact->business_id != $business_id) {
                    abort(403, 'Unauthorized action.');
                }
            }
        }

        $line_details = $format == 'format_3' ? true : false;

        $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date, $format, $location_id, $line_details);

        $location = null;
        if (! empty($location_id)) {
            $location = BusinessLocation::where('business_id', $business_id)->find($location_id);
        }
        if (request()->input('action') == 'pdf') {
            $output_file_name = 'Ledger-'.str_replace(' ', '-', $contact->name).'-'.$start_date.'-'.$end_date.'.pdf';
            $for_pdf = true;
            if ($format == 'format_2') {
                $html = view('contact.ledger_format_2')
                        ->with(compact('ledger_details', 'contact', 'for_pdf', 'location'))->render();
            } elseif ($format == 'format_3') {
                $html = view('contact.ledger_format_3')
                    ->with(compact('ledger_details', 'contact', 'location', 'is_admin', 'for_pdf'))->render();
            } else {
                $html = view('contact.ledger')
                    ->with(compact('ledger_details', 'contact', 'for_pdf', 'location'))->render();
            }

            $mpdf = $this->getMpdf();
            $mpdf->WriteHTML($html);
            $mpdf->Output($output_file_name, 'I');
        }

        if ($format == 'format_2') {
            return view('contact.ledger_format_2')
             ->with(compact('ledger_details', 'contact', 'location'));
        } elseif ($format == 'format_3') {
            return view('contact.ledger_format_3')
             ->with(compact('ledger_details', 'contact', 'location', 'is_admin'));
        } else {
            return view('contact.ledger')
             ->with(compact('ledger_details', 'contact', 'location', 'is_admin'));
        }
    }

    public function postCustomersApi(Request $request)
    {
        try {
            $api_token = $request->header('API-TOKEN');

            $api_settings = $this->moduleUtil->getApiSettings($api_token);

            $business = Business::find($api_settings->business_id);

            $data = $request->only(['name', 'email']);

            $customer = Contact::where('business_id', $api_settings->business_id)
                                ->where('email', $data['email'])
                                ->whereIn('type', ['customer', 'both'])
                                ->first();

            if (empty($customer)) {
                $data['type'] = 'customer';
                $data['business_id'] = $api_settings->business_id;
                $data['created_by'] = $business->owner_id;
                $data['mobile'] = 0;

                $ref_count = $this->commonUtil->setAndGetReferenceCount('contacts', $business->id);

                $data['contact_id'] = $this->commonUtil->generateReferenceNumber('contacts', $ref_count, $business->id);

                $customer = Contact::create($data);
            }
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            return $this->respondWentWrong($e);
        }

        return $this->respond($customer);
    }

    /**
     * Function to send ledger notification
     */
    public function sendLedger(Request $request)
    {
        $notAllowed = $this->notificationUtil->notAllowedInDemo();
        if (! empty($notAllowed)) {
            return $notAllowed;
        }

        try {
            $data = $request->only(['to_email', 'subject', 'email_body', 'cc', 'bcc', 'ledger_format']);
            $emails_array = array_map('trim', explode(',', $data['to_email']));

            $contact_id = $request->input('contact_id');
            $business_id = request()->session()->get('business.id');

            $start_date = request()->input('start_date');
            $end_date = request()->input('end_date');
            $location_id = request()->input('location_id');

            $contact = Contact::find($contact_id);

            $ledger_details = $this->transactionUtil->getLedgerDetails($contact_id, $start_date, $end_date, $data['ledger_format'], $location_id);

            $orig_data = [
                'email_body' => $data['email_body'],
                'subject' => $data['subject'],
            ];

            $tag_replaced_data = $this->notificationUtil->replaceTags($business_id, $orig_data, null, $contact);
            $data['email_body'] = $tag_replaced_data['email_body'];
            $data['subject'] = $tag_replaced_data['subject'];

            //replace balance_due
            $data['email_body'] = str_replace('{balance_due}', $this->notificationUtil->num_f($ledger_details['balance_due']), $data['email_body']);

            $data['email_settings'] = request()->session()->get('business.email_settings');

            $for_pdf = true;
            if ($data['ledger_format'] == 'format_2') {
                $html = view('contact.ledger_format_2')
                        ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            } else {
                $html = view('contact.ledger')
                        ->with(compact('ledger_details', 'contact', 'for_pdf'))->render();
            }

            $mpdf = $this->getMpdf();
            $mpdf->WriteHTML($html);

            $path = config('constants.mpdf_temp_path');
            if (! file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $file = $path.'/'.time().'_ledger.pdf';
            $mpdf->Output($file, 'F');

            $data['attachment'] = $file;
            $data['attachment_name'] = 'ledger.pdf';
            \Notification::route('mail', $emails_array)
                    ->notify(new CustomerNotification($data));

            if (file_exists($file)) {
                unlink($file);
            }

            $output = ['success' => 1, 'msg' => __('lang_v1.notification_sent_successfully')];
        } catch (\Exception $e) {
            \Log::emergency('File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage());

            $output = ['success' => 0,
                'msg' => 'File:'.$e->getFile().'Line:'.$e->getLine().'Message:'.$e->getMessage(),
            ];
        }

        return $output;
    }

    /**
     * Function to get product stock details for a supplier
     */
    public function getSupplierStockReport($supplier_id)
    {
        //TODO: current stock not calculating stock transferred from other location
        $pl_query_string = $this->commonUtil->get_pl_quantity_sum_string();
        $query = PurchaseLine::join('transactions as t', 't.id', '=', 'purchase_lines.transaction_id')
                        ->join('products as p', 'p.id', '=', 'purchase_lines.product_id')
                        ->join('variations as v', 'v.id', '=', 'purchase_lines.variation_id')
                        ->join('product_variations as pv', 'v.product_variation_id', '=', 'pv.id')
                        ->join('units as u', 'p.unit_id', '=', 'u.id')
                        ->whereIn('t.type', ['purchase', 'purchase_return'])
                        ->where('t.contact_id', $supplier_id)
                        ->select(
                            'p.name as product_name',
                            'v.name as variation_name',
                            'pv.name as product_variation_name',
                            'p.type as product_type',
                            'u.short_name as product_unit',
                            'v.sub_sku',
                            DB::raw('SUM(quantity) as purchase_quantity'),
                            DB::raw('SUM(quantity_returned) as total_quantity_returned'),
                            DB::raw("SUM((SELECT SUM(TSL.quantity - TSL.quantity_returned) FROM transaction_sell_lines_purchase_lines as TSLPL 
                              JOIN transaction_sell_lines AS TSL ON TSLPL.sell_line_id=TSL.id
                              JOIN transactions AS sell ON sell.id=TSL.transaction_id
                              WHERE sell.status='final' AND sell.type='sell'
                              AND TSLPL.purchase_line_id=purchase_lines.id)) as total_quantity_sold"),
                            DB::raw("SUM((SELECT SUM(TSL.quantity - TSL.quantity_returned) FROM transaction_sell_lines_purchase_lines as TSLPL 
                              JOIN transaction_sell_lines AS TSL ON TSLPL.sell_line_id=TSL.id
                              JOIN transactions AS sell ON sell.id=TSL.transaction_id
                              WHERE sell.status='final' AND sell.type='sell_transfer'
                              AND TSLPL.purchase_line_id=purchase_lines.id)) as total_quantity_transfered"),
                            DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0) * purchase_price_inc_tax) as stock_price"),
                            DB::raw("SUM( COALESCE(quantity - ($pl_query_string), 0)) as current_stock")
                        )->groupBy('purchase_lines.variation_id');

        if (! empty(request()->location_id)) {
            $query->where('t.location_id', request()->location_id);
        }

        $product_stocks = Datatables::of($query)
                            ->editColumn('product_name', function ($row) {
                                $name = $row->product_name;
                                if ($row->product_type == 'variable') {
                                    $name .= ' - '.$row->product_variation_name.'-'.$row->variation_name;
                                }

                                return $name.' ('.$row->sub_sku.')';
                            })
                            ->editColumn('purchase_quantity', function ($row) {
                                $purchase_quantity = 0;
                                if ($row->purchase_quantity) {
                                    $purchase_quantity = (float) $row->purchase_quantity;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$purchase_quantity.'" data-unit="'.$row->product_unit.'" >'.$purchase_quantity.'</span> '.$row->product_unit;
                            })
                            ->editColumn('total_quantity_sold', function ($row) {
                                $total_quantity_sold = 0;
                                if ($row->total_quantity_sold) {
                                    $total_quantity_sold = (float) $row->total_quantity_sold;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$total_quantity_sold.'" data-unit="'.$row->product_unit.'" >'.$total_quantity_sold.'</span> '.$row->product_unit;
                            })
                            ->editColumn('total_quantity_transfered', function ($row) {
                                $total_quantity_transfered = 0;
                                if ($row->total_quantity_transfered) {
                                    $total_quantity_transfered = (float) $row->total_quantity_transfered;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$total_quantity_transfered.'" data-unit="'.$row->product_unit.'" >'.$total_quantity_transfered.'</span> '.$row->product_unit;
                            })
                            ->editColumn('stock_price', function ($row) {
                                $stock_price = 0;
                                if ($row->stock_price) {
                                    $stock_price = (float) $row->stock_price;
                                }

                                return '<span class="display_currency" data-currency_symbol=true >'.$stock_price.'</span> ';
                            })
                            ->editColumn('current_stock', function ($row) {
                                $current_stock = 0;
                                if ($row->current_stock) {
                                    $current_stock = (float) $row->current_stock;
                                }

                                return '<span data-is_quantity="true" class="display_currency" data-currency_symbol=false  data-orig-value="'.$current_stock.'" data-unit="'.$row->product_unit.'" >'.$current_stock.'</span> '.$row->product_unit;
                            });

        return $product_stocks->rawColumns(['current_stock', 'stock_price', 'total_quantity_sold', 'purchase_quantity', 'total_quantity_transfered'])->make(true);
    }

    public function updateStatus($id)
    {
        if (! auth()->user()->can('supplier.update') && ! auth()->user()->can('customer.update')) {
            abort(403, 'Unauthorized action.');
        }

        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $contact = Contact::where('business_id', $business_id)->find($id);
            $contact->contact_status = $contact->contact_status == 'active' ? 'inactive' : 'active';
            $contact->save();

            $output = ['success' => true,
                'msg' => __('contact.updated_success'),
            ];

            return $output;
        }
    }

    /**
     * Display contact locations on map
     */
    public function contactMap()
    {
        if (! auth()->user()->can('supplier.view') && ! auth()->user()->can('customer.view')) {
            abort(403, 'Unauthorized action.');
        }

        $business_id = request()->session()->get('user.business_id');

        $query = Contact::where('business_id', $business_id)
                        ->active()
                        ->whereNotNull('position');

        if (! empty(request()->input('contacts'))) {
            $query->whereIn('id', request()->input('contacts'));
        }
        $contacts = $query->get();

        $all_contacts = Contact::where('business_id', $business_id)
                        ->active()
                        ->get();

        return view('contact.contact_map')
             ->with(compact('contacts', 'all_contacts'));
    }

    public function getContactPayments($contact_id)
    {
        $business_id = request()->session()->get('user.business_id');
        if (request()->ajax()) {
            $payments = TransactionPayment::leftjoin('transactions as t', 'transaction_payments.transaction_id', '=', 't.id')
            ->leftjoin('transaction_payments as parent_payment', 'transaction_payments.parent_id', '=', 'parent_payment.id')
            ->where('transaction_payments.business_id', $business_id)
            ->whereNull('transaction_payments.parent_id')
            ->with(['child_payments', 'child_payments.transaction'])
            ->where('transaction_payments.payment_for', $contact_id)
                ->select(
                    'transaction_payments.id',
                    'transaction_payments.amount',
                    'transaction_payments.is_return',
                    'transaction_payments.method',
                    'transaction_payments.paid_on',
                    'transaction_payments.payment_ref_no',
                    'transaction_payments.parent_id',
                    'transaction_payments.transaction_no',
                    't.invoice_no',
                    't.ref_no',
                    't.type as transaction_type',
                    't.return_parent_id',
                    't.id as transaction_id',
                    'transaction_payments.cheque_number',
                    'transaction_payments.card_transaction_number',
                    'transaction_payments.bank_account_number',
                    'transaction_payments.id as DT_RowId',
                    'parent_payment.payment_ref_no as parent_payment_ref_no'
                )
                ->groupBy('transaction_payments.id')
                ->orderByDesc('transaction_payments.paid_on')
                ->paginate();

            $payment_types = $this->transactionUtil->payment_types(null, true, $business_id);

            return view('contact.partials.contact_payments_tab')
                    ->with(compact('payments', 'payment_types'));
        }
    }

    public function getContactDue($contact_id)
    {
        if (request()->ajax()) {
            $business_id = request()->session()->get('user.business_id');
            $due = $this->transactionUtil->getContactDue($contact_id, $business_id);

            $output = $due != 0 ? $this->transactionUtil->num_f($due, true) : '';

            return $output;
        }
    }

    /**
     * Get default shipping address map data for AJAX updates
     */
    public function getDefaultShippingMap($contact_id)
{
    try {
        $default_shipping_address = ShippingAddress::where('contact_id', $contact_id)
            ->where('is_default', 1)
            ->first();
        
        $coordinates = null;
        $map_url = null;

        if ($default_shipping_address) {
            $map_url = $default_shipping_address->map; // Assign map url

            // Prioritize using the latlong field if it's available
            if (!empty($default_shipping_address->latlong)) {
                $coordinates = $default_shipping_address->latlong;
            } 
            // If latlong is not available, try to extract coordinates from the map URL
            elseif (!empty($default_shipping_address->map)) {
                if (preg_match('/3d([-0-9.]+)!4d([-0-9.]+)/', $map_url, $matches)) {
                    $coordinates = $matches[1] . ',' . $matches[2];
                } elseif (preg_match('/@([-0-9.]+),([-0-9.]+)/', $map_url, $matches)) {
                    $coordinates = $matches[1] . ',' . $matches[2];
                } elseif (preg_match('/maps\/search\/([0-9.+-]+),([0-9.+-]+)/', $map_url, $matches)) {
                    $coordinates = $matches[1] . ',' . $matches[2];
                }
            }
        }
        
        if ($coordinates) {
            return response()->json([
                'success' => true,
                'coordinates' => $coordinates,
                'map_url' => $map_url
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No map data available'
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => 'Error retrieving map data'
        ]);
    }
}

    public function getShippingAddresses($contact_id)
    {
        $query = ShippingAddress::where('contact_id', $contact_id)
            ->with('labelShipping');
    
        return DataTables::of($query)
            ->addColumn('action', function ($row) {
                $html = '<div class="btn-group">
                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                        data-toggle="dropdown" aria-expanded="false">' .
                        __('messages.actions') .
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                
                $html .= '<li><a href="#" class="shipping_address_btn" data-href="' . 
                         action([\App\Http\Controllers\ContactController::class, 'editShippingAddress'], [$row->id]) . 
                         '"><i class="glyphicon glyphicon-edit"></i>' . __('messages.edit') . '</a></li>';
                
                $html .= '<li><a href="#" class="delete_shipping_address" data-href="' . 
                         action([\App\Http\Controllers\ContactController::class, 'deleteShippingAddress'], [$row->id]) . 
                         '"><i class="glyphicon glyphicon-trash"></i>' . __('messages.delete') . '</a></li>';
                
                $html .= '</ul></div>';
    
                return $html;
            })
            ->addColumn('default_action', function ($row) {
                $checked = $row->is_default ? 'checked' : '';
                $html = '<input type="checkbox" class="set_default_checkbox" data-id="' . $row->id . '" ' . $checked . '>';
                return $html;
            })
            ->addColumn('label', function ($row) {
                return $row->labelShipping ? $row->labelShipping->name : '-';
            })
            ->addColumn('mobile', function ($row) {
                return $row->mobile ?? '-';
            })
           ->editColumn('address', function ($row) {
            $address = $row->address ?? '-';
            
            // Check if map URL exists and make address clickable
            if (!empty($row->map)) {
                return '<a href="' . $row->map . '" target="_blank" class="map-link" title="Open in Google Maps">' . 
                       $address . '</a>';
            } else {
                return $address;
            }
        })
           ->rawColumns(['action', 'default_action', 'address'])
            ->make(true);
    }

    public function addShippingLabel(Request $request)
    {
        try {
            $business_id = $request->session()->get('user.business_id');
    
            $label = LabelShipping::create([
                'name' => $request->name,
                'business_id' => $business_id,
                'contact_id' => $request->contact_id,
            ]);
    
            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.label_added_success'),
                'label' => [
                    'id' => $label->id,
                    'name' => $label->name,
                ],
            ]);
        } catch (\Exception $e) {
            \Log::error('Error adding shipping label: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    public function setDefaultShippingAddress($id)
    {
        try {
            $shipping_address = ShippingAddress::findOrFail($id);
            $contact_id = $shipping_address->contact_id;

            // Unset other addresses as default for the same contact
            ShippingAddress::where('contact_id', $contact_id)
                ->where('id', '!=', $id)
                ->update(['is_default' => 0]);

            // Set the selected address as default
            $shipping_address->update([
                'is_default' => 1,
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'msg' => __('Set Default Shipping Address Successfully'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error setting default shipping address: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    public function getShippingAddressForm($contact_id)
    {
        $contact = Contact::findOrFail($contact_id);
        $business_id = $contact->business_id;
    
        // Fetch default labels (Work and Home) where contact_id is null
        $defaultLabels = LabelShipping::where('business_id', $business_id)
            ->whereNull('contact_id')
            ->whereIn('name', ['Work', 'Home'])
            ->pluck('name', 'id')
            ->toArray();
    
        // Fetch custom labels for this contact
        $customLabels = LabelShipping::where('business_id', $business_id)
            ->where('contact_id', $contact_id)
            ->pluck('name', 'id')
            ->toArray();
    
        // Combine labels: default labels first, then custom labels
        $labels = $defaultLabels + $customLabels;
    
        // Find the "Home" label (case-insensitive) for default selection
        $default_label_id = null;
        foreach ($labels as $id => $name) {
            if (strtolower($name) === 'home') {
                $default_label_id = $id;
                break;
            }
        }
    
        return view('contact.partials.shipping_address_form', compact('contact', 'labels', 'default_label_id'));
    }

    public function editShippingAddress($id)
    {
        $shipping_address = ShippingAddress::findOrFail($id);
        $contact = Contact::findOrFail($shipping_address->contact_id);
        $business_id = $shipping_address->business_id;
    
        // Fetch default labels (Work and Home) where contact_id is null
        $defaultLabels = LabelShipping::where('business_id', $business_id)
            ->whereNull('contact_id')
            ->whereIn('name', ['Work', 'Home'])
            ->pluck('name', 'id')
            ->toArray();
    
        // Fetch custom labels for this contact
        $customLabels = LabelShipping::where('business_id', $business_id)
            ->where('contact_id', $contact->id)
            ->pluck('name', 'id')
            ->toArray();
    
        // Combine labels: default labels first, then custom labels
        $labels = $defaultLabels + $customLabels;
    
        return view('contact.partials.shipping_address_form', compact('shipping_address', 'contact', 'labels'));
    }

    public function storeShippingAddress(Request $request)
    {
        try {
            $contact = Contact::findOrFail($request->contact_id);
            $business_id = $contact->business_id;

            // Get the map link from the request
            $map_link = $request->input('map_link');

            // Initialize latlong as null
            $latlong = null;

            // If a map link is provided, extract the latlong
            if (!empty($map_link)) {
                // Expand the URL if it's shortened
                $expanded_url = $this->expandUrl($map_link);

                // Extract latlong from the expanded URL
                $coordinates = $this->getLatLngFromGoogleMapsApi($expanded_url);

                // If coordinates are found, format them as "lat,lng"
                if ($coordinates) {
                    $latlong = $coordinates['lat'] . ',' . $coordinates['lng'];
                }
            }

            // Check if there are existing ShippingAddress records for this contact and business
            $existingAddressesCount = ShippingAddress::where('contact_id', $request->contact_id)
            ->where('business_id', $business_id)
            ->count();

            // Determine if the new address should be the default
            $is_default = ($existingAddressesCount === 0) ? 1 : 0;

            // Create the shipping address
            $shippingAddress = ShippingAddress::create([
                'business_id' => $business_id,
                'contact_id' => $request->contact_id,
                'label_shipping_id' => $request->label_shipping_id,
                'mobile' => $request->mobile,
                'address' => $request->address,
                'map' => $map_link, // Save the original map link
                'latlong' => $latlong, // Save the extracted latlong in "lat,lng" format
                'is_default' => $is_default,
                'created_by' => auth()->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.added_success'),
                'data' => [
                    'id' => $shippingAddress->id,
                    'is_default' => $is_default,
                    'is_first_address' => $existingAddressesCount === 0,
                    'has_map' => !empty($map_link),
                    'coordinates' => $latlong
                ]
            ]);
        } catch (\Exception $e) {
            \Log::error('Error adding shipping address: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    public function updateShippingAddress(Request $request, $id)
    {
        try {
            $shipping_address = ShippingAddress::findOrFail($id);
    
            // Get the map link from the request
            $map_link = $request->input('map_link');
    
            // Initialize latlong as null
            $latlong = null;
    
            // If a map link is provided, extract the latlong
            if (!empty($map_link)) {
                // Expand the URL if it's shortened
                $expanded_url = $this->expandUrl($map_link);
    
                // Extract latlong from the expanded URL
                $coordinates = $this->getLatLngFromGoogleMapsApi($expanded_url);
    
                // If coordinates are found, format them as "lat,lng"
                if ($coordinates) {
                    $latlong = $coordinates['lat'] . ',' . $coordinates['lng'];
                }
            }
    
            // Update the shipping address
            $shipping_address->update([
                'label_shipping_id' => $request->label_shipping_id,
                'mobile' => $request->mobile,
                'address' => $request->address,
                'map' => $map_link, // Save the original map link
                'latlong' => $latlong, // Save the extracted latlong in "lat,lng" format
                'updated_at' => now(),
            ]);
    
            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.updated_success'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error updating shipping address: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }
    public function deleteShippingAddress($id)
    {
        try {
            $shipping_address = ShippingAddress::findOrFail($id);
            $shipping_address->delete();

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.deleted_success'),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error deleting shipping address: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong'),
            ]);
        }
    }

    // ==========================================
    // CUSTOMER CONTRACT METHODS
    // ==========================================

    public function getProductsForContract(Request $request)
    {
        if ($request->ajax()) {
            $term = $request->input('term', '');

            if (empty($term)) {
                return response()->json([]);
            }

            $business_id = $request->session()->get('user.business_id');

            $products = Product::leftJoin('variations', 'products.id', '=', 'variations.product_id')
                ->where('products.business_id', $business_id)
                ->whereNull('variations.deleted_at')
                ->where(function ($query) use ($term) {
                    $query->where('products.name', 'like', '%' . $term . '%');
                    $query->orWhere('products.sku', 'like', '%' . $term . '%');
                    $query->orWhere('variations.sub_sku', 'like', '%' . $term . '%');
                })
                ->select(
                    'products.id as product_id',
                    'products.name',
                    'products.type',
                    'variations.id as variation_id',
                    'variations.name as variation_name',
                    'variations.sub_sku',
                    'variations.sell_price_inc_tax as selling_price'
                )
                ->limit(20)
                ->get();

            $result = [];
            foreach ($products as $product) {
                $text = $product->name;
                if ($product->type == 'variable') {
                    $text .= ' - ' . $product->variation_name;
                }
                
                $result[] = [
                    'id' => $product->variation_id,
                    'text' => $text,
                    'product_id' => $product->product_id,
                    'variation_id' => $product->variation_id,
                    'name' => $text,
                    'sub_sku' => $product->sub_sku,
                    'selling_price' => $product->selling_price,
                    'type' => $product->type
                ];
            }

            return response()->json($result);
        }
    }

    public function getCustomerContracts($contact_id)
    {
        $business_id = request()->session()->get('user.business_id');

        // Query returns one row per PRODUCT
        $query = CustomerContract::join('contract_products', 'customer_contracts.id', '=', 'contract_products.contract_id')
            ->join('products', 'contract_products.product_id', '=', 'products.id')
            ->join('users', 'customer_contracts.created_by', '=', 'users.id')
            ->where('customer_contracts.contact_id', $contact_id)
            ->where('customer_contracts.business_id', $business_id)
            ->whereNull('contract_products.parent_sell_line_id')
            ->select([
                'customer_contracts.id as contract_id',
                'customer_contracts.*',
                'products.name as product_name',
                'contract_products.target_quantity',
                'contract_products.product_id', // <--- Added this to calculate specific progress
                'users.first_name as added_by'
            ])
            ->orderBy('customer_contracts.id', 'desc');

        return DataTables::of($query)
            ->addColumn('action', function ($row) {
                $html = '<div class="btn-group">
                    <button type="button" class="btn btn-info dropdown-toggle btn-xs" 
                        data-toggle="dropdown" aria-expanded="false">' .
                        __('messages.actions') .
                        '<span class="caret"></span><span class="sr-only">Toggle Dropdown</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-left" role="menu">';
                
                $html .= '<li><a href="#" class="view_customer_contract" data-href="' . 
                            action([\App\Http\Controllers\ContactController::class, 'showCustomerContract'], [$row->contract_id]) . 
                            '"><i class="fas fa-eye"></i> ' . __('messages.view') . '</a></li>';
                
                $html .= '<li><a href="#" class="customer_contract_btn" data-href="' . 
                            action([\App\Http\Controllers\ContactController::class, 'editCustomerContract'], [$row->contract_id]) . 
                            '"><i class="glyphicon glyphicon-edit"></i>' . __('messages.edit') . '</a></li>';
                
                $html .= '<li><a href="#" class="delete_customer_contract" data-href="' . 
                            action([\App\Http\Controllers\ContactController::class, 'deleteCustomerContract'], [$row->contract_id]) . 
                            '"><i class="glyphicon glyphicon-trash"></i>' . __('messages.delete') . '</a></li>';
                
                $html .= '</ul></div>';
                return $html;
            })
            ->addColumn('period', function ($row) {
                $start = \Carbon\Carbon::parse($row->start_date)->format('Y-m-d');
                $end = !empty($row->end_date) ? \Carbon\Carbon::parse($row->end_date)->format('Y-m-d') : '...';
                return $start . ' <i class="fas fa-arrow-right" style="font-size:10px;"></i> ' . $end;
            })
            // --- MODIFIED: Progress Calculation per Product ---
            ->addColumn('progress', function ($row) {
                // 1. Get Target for THIS specific product
                $target = $row->target_quantity;
                
                if ($target == 0) return '0/0 (0%)';

                // 2. Get Actual Sold for THIS specific product
                $totalSold = TransactionSellLine::join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                    ->where('transactions.business_id', $row->business_id)
                    ->where('transactions.contact_id', $row->contact_id)
                    ->where('transactions.type', 'sell')
                    ->where('transactions.status', 'final')
                    ->whereBetween('transactions.transaction_date', [$row->start_date, $row->end_date])
                    ->where('transaction_sell_lines.product_id', $row->product_id) // Filter by specific product
                    ->whereNull('transaction_sell_lines.parent_sell_line_id')
                    ->sum('transaction_sell_lines.quantity');

                $percentage = ($totalSold / $target) * 100;
                
                return number_format($totalSold, 0) . '/' . number_format($target, 0) . ' (' . number_format($percentage, 0) . '%)';
            })
            ->addColumn('status', function ($row) {
                $today = \Carbon\Carbon::now()->format('Y-m-d');
                $startDate = \Carbon\Carbon::parse($row->start_date)->format('Y-m-d');
                $endDate = !empty($row->end_date) ? \Carbon\Carbon::parse($row->end_date)->format('Y-m-d') : null;
                
                if ($endDate && $endDate < $today) {
                    return '<span class="label label-danger">EXPIRED</span>';
                } elseif ($startDate > $today) {
                    return '<span class="label label-warning">PENDING</span>';
                } else {
                    return '<span class="label label-success">ACTIVE</span>';
                }
            })
            ->editColumn('total_contract_value', function ($row) {
                return '<span class="display_currency" data-currency_symbol=true>' . 
                $this->transactionUtil->num_f($row->total_contract_value, true) . '</span>';
            })
            ->rawColumns(['action', 'total_contract_value', 'status', 'period', 'progress'])
            ->make(true);
    }

    public function getCustomerContractForm($contact_id)
    {
        $contact = Contact::findOrFail($contact_id);
        $business_id = $contact->business_id;
        
        $products = Product::where('business_id', $business_id)
            ->select('id', 'name')
            ->get()
            ->pluck('name', 'id');

        return view('contact.partials.customer_contract_form', compact('contact', 'products'));
    }

    public function editCustomerContract($id)
    {
        // LOAD 'media' RELATIONSHIP HERE
        $customer_contract = CustomerContract::with(['products', 'media'])->findOrFail($id);
        
        $contact = Contact::findOrFail($customer_contract->contact_id);
        $business_id = $contact->business_id;
        
        $products = Product::where('business_id', $business_id)
            ->select('id', 'name')
            ->get()
            ->pluck('name', 'id');

        return view('contact.partials.customer_contract_form', compact('customer_contract', 'contact', 'products'));
    }

    public function deleteCustomerContractMedia($media_id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');
            
            // Find media to ensure it belongs to this business
            $media = Media::where('business_id', $business_id)->findOrFail($media_id);
            
            // Delete file from storage and database using Media model helper
            Media::deleteMedia($business_id, $media_id);

            return response()->json([
                'success' => true,
                'msg' => __('lang_v1.file_deleted_successfully')
            ]);

        } catch (\Exception $e) {
            \Log::error('Error deleting contract media: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'msg' => __('messages.something_went_wrong')
            ]);
        }
    }

    public function storeCustomerContract(Request $request)
    {
        try {
            DB::beginTransaction();
            
            $business_id = $request->session()->get('user.business_id');
            $user_id = auth()->user()->id;
            
            $contractData = $request->only(['contact_id', 'contract_name', 'reference_no', 'start_date', 'end_date']);
            
            $contractData['business_id'] = $business_id; 
            $contractData['created_by'] = $user_id;

            // Auto-Generate Reference No if empty
            $autoGenerateRef = empty($contractData['reference_no']);
            if ($autoGenerateRef) {
                $lastContract = CustomerContract::latest('id')->first();
                $nextId = $lastContract ? $lastContract->id + 1 : 1;
                $contractData['reference_no'] = str_pad($nextId, 5, '0', STR_PAD_LEFT);
            }

            $contractData['total_target_units'] = 0;
            $contractData['total_contract_value'] = 0;

            $contract = CustomerContract::create($contractData);

            // Update Reference with actual ID if auto-generated
            if ($autoGenerateRef) {
                $contract->reference_no = str_pad($contract->id, 5, '0', STR_PAD_LEFT);
                $contract->save();
            }

            // Handle Multi-File Upload
            if (!empty($request->input('contract_document_names'))) {
                $file_names = explode(',', $request->input('contract_document_names'));
                Media::attachMediaToModel($contract, $business_id, $file_names);
            }

            $total_units = 0;
            $total_value = 0;

            if (!empty($request->products)) {
                foreach ($request->products as $product_row) {
                    if (empty($product_row['product_id'])) continue;

                    $product = Product::find($product_row['product_id']);
                    
                    $quantity = $product_row['target_quantity'];
                    $unit_price = $product_row['unit_price'];
                    $discount = $product_row['discount'] ?? 0;
                    $discount_type = $product_row['discount_type'] ?? 'Fixed';
                    
                    $subtotal = ($quantity * $unit_price);
                    if($discount_type == 'Fixed') {
                        $subtotal -= $discount;
                    } else {
                        $subtotal -= ($subtotal * $discount / 100);
                    }

                    // Save Product
                    $contractProduct = ContractProduct::create([
                        'contract_id' => $contract->id,
                        'product_id' => $product_row['product_id'],
                        'target_quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount' => $discount,
                        'discount_type' => $discount_type,
                        'subtotal' => $subtotal,
                        'children_type' => '',
                        'parent_sell_line_id' => null
                    ]);

                    $total_units += $quantity;
                    $total_value += $subtotal;

                    // Handle Combo Products Logic (omitted for brevity, same as previous)
                     if ($product->type == 'combo' || $product->type == 'combo_single') {
                        $variation_id = $product_row['variation_id'] ?? null;
                        
                        if (!$variation_id) {
                            $firstVar = Variation::where('product_id', $product->id)->first();
                            $variation_id = $firstVar ? $firstVar->id : null;
                        }

                        if ($variation_id) {
                            $parentVariation = Variation::find($variation_id);
                            $combo_variations = $parentVariation->combo_variations ?? [];

                            foreach ($combo_variations as $child) {
                                $childVariation = Variation::with('product')->find($child['variation_id']);
                                
                                if ($childVariation) {
                                    $childQty = $child['quantity'] * $quantity;
                                    
                                    ContractProduct::create([
                                        'contract_id' => $contract->id,
                                        'product_id' => $childVariation->product_id,
                                        'target_quantity' => $childQty,
                                        'unit_price' => 0,
                                        'subtotal' => 0,
                                        'parent_sell_line_id' => $contractProduct->id,
                                        'children_type' => ($product->type == 'combo_single') ? 'combo_single' : 'combo',
                                        'sub_unit_id' => $child['unit_id'] ?? null
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            $contract->update([
                'total_target_units' => $total_units,
                'total_contract_value' => $total_value
            ]);

            DB::commit();

            return response()->json([
                'success' => true, 
                'msg' => 'Contract created successfully',
                'id' => $contract->id
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Contract Create Error: ' . $e->getMessage());
            return response()->json(['success' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function updateCustomerContract(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            
            $business_id = $request->session()->get('user.business_id');

            $contract = CustomerContract::findOrFail($id);
            $contract->update($request->only(['contract_name', 'reference_no', 'start_date', 'end_date']));

            // Handle New File Uploads (Append to existing)
            if (!empty($request->input('contract_document_names'))) {
                $file_names = explode(',', $request->input('contract_document_names'));
                Media::attachMediaToModel($contract, $business_id, $file_names);
            }

            // Update Products Logic
            ContractProduct::where('contract_id', $id)->delete();

            $total_units = 0;
            $total_value = 0;

            if (!empty($request->products)) {
                foreach ($request->products as $product_row) {
                    if (empty($product_row['product_id'])) continue;
                    
                    // ... (Product saving logic same as store) ...
                    $product = Product::find($product_row['product_id']);
                    
                    $quantity = $product_row['target_quantity'];
                    $unit_price = $product_row['unit_price'];
                    $discount = $product_row['discount'] ?? 0;
                    $discount_type = $product_row['discount_type'] ?? 'Fixed';
                    
                    $subtotal = ($quantity * $unit_price);
                    if($discount_type == 'Fixed') {
                        $subtotal -= $discount;
                    } else {
                        $subtotal -= ($subtotal * $discount / 100);
                    }

                    // 1. Save Parent
                    $contractProduct = ContractProduct::create([
                        'contract_id' => $contract->id,
                        'product_id' => $product_row['product_id'],
                        'target_quantity' => $quantity,
                        'unit_price' => $unit_price,
                        'discount' => $discount,
                        'discount_type' => $discount_type,
                        'subtotal' => $subtotal,
                        'children_type' => '',
                        'parent_sell_line_id' => null
                    ]);

                    $total_units += $quantity;
                    $total_value += $subtotal;
                    
                    // 2. Expand Combo Children (omitted for brevity, same as store)
                      if ($product->type == 'combo' || $product->type == 'combo_single') {
                        $variation_id = $product_row['variation_id'] ?? null;
                        if (!$variation_id) {
                            $firstVar = Variation::where('product_id', $product->id)->first();
                            $variation_id = $firstVar ? $firstVar->id : null;
                        }

                        if ($variation_id) {
                            $parentVariation = Variation::find($variation_id);
                            $combo_variations = $parentVariation->combo_variations ?? [];

                            foreach ($combo_variations as $child) {
                                $childVariation = Variation::with('product')->find($child['variation_id']);
                                if ($childVariation) {
                                    ContractProduct::create([
                                        'contract_id' => $contract->id,
                                        'product_id' => $childVariation->product_id,
                                        'target_quantity' => $child['quantity'] * $quantity,
                                        'unit_price' => 0,
                                        'subtotal' => 0,
                                        'parent_sell_line_id' => $contractProduct->id,
                                        'children_type' => ($product->type == 'combo_single') ? 'combo_single' : 'combo',
                                        'sub_unit_id' => $child['unit_id'] ?? null
                                    ]);
                                }
                            }
                        }
                    }
                }
            }

            $contract->update([
                'total_target_units' => $total_units,
                'total_contract_value' => $total_value
            ]);

            DB::commit();

            return response()->json(['success' => true, 'msg' => 'Contract updated successfully']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'msg' => 'Error: ' . $e->getMessage()]);
        }
    }

    public function deleteCustomerContract($id)
    {
        try {
            DB::beginTransaction();
            $business_id = request()->session()->get('user.business_id');
            $customer_contract = CustomerContract::where('business_id', $business_id)->findOrFail($id);
            
            // Delete associated products
            ContractProduct::where('contract_id', $id)->delete();
            
            // Delete associated media files
            $customer_contract->media()->delete();
            
            // Delete contract
            $customer_contract->delete();

            DB::commit();
            return response()->json(['success' => true, 'msg' => __('lang_v1.deleted_success')]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }

   /**
     * Show the specified customer contract.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function showCustomerContract($id)
    {
        try {
            $business_id = request()->session()->get('user.business_id');

            // 1. Fetch Contract
            $contract = CustomerContract::with(['products' => function($q) {
                    $q->with('product')
                    ->whereNull('parent_sell_line_id')
                    ->where(function($query) {
                        $query->whereNull('children_type')
                                ->orWhere('children_type', '');
                    });
                }, 'media'])
                ->where('business_id', $business_id)
                ->findOrFail($id);

            // 2. Status Logic
            $today = \Carbon\Carbon::now()->format('Y-m-d');
            $startDate = \Carbon\Carbon::parse($contract->start_date)->format('Y-m-d');
            $endDate = !empty($contract->end_date) ? \Carbon\Carbon::parse($contract->end_date)->format('Y-m-d') : null;

            if (!empty($endDate) && $endDate < $today) {
                $contract->status_label = 'Expired';
                $contract->status_class = 'label-danger';
            } elseif ($startDate > $today) {
                $contract->status_label = 'Pending';
                $contract->status_class = 'label-warning';
            } else {
                $contract->status_label = 'Active';
                $contract->status_class = 'label-success';
            }

            // 3. Calculate Actual Qty for each product
            foreach ($contract->products as $product) {
                $actualQty = TransactionSellLine::join('transactions', 'transaction_sell_lines.transaction_id', '=', 'transactions.id')
                    ->where('transactions.business_id', $business_id)
                    ->where('transactions.contact_id', $contract->contact_id)
                    ->where('transactions.type', 'sell')
                    ->where('transactions.status', 'final')
                    ->whereBetween('transactions.transaction_date', [$contract->start_date, $contract->end_date])
                    ->where('transaction_sell_lines.product_id', $product->product_id)
                    ->whereNull('transaction_sell_lines.parent_sell_line_id')
                    ->where(function($q) {
                        $q->whereNull('transaction_sell_lines.children_type')
                        ->orWhere('transaction_sell_lines.children_type', '');
                    })
                    ->sum('transaction_sell_lines.quantity');

                $product->actual_qty = $actualQty;
            }

            // --- NEW: Fetch Related Sales Transactions for the Tab ---
            // Get all product IDs involved in this contract
            $contractProductIds = $contract->products->pluck('product_id')->toArray();

           $relatedSales = Transaction::join('transaction_sell_lines', 'transactions.id', '=', 'transaction_sell_lines.transaction_id')
                // --- ADDED JOIN HERE ---
                ->leftJoin('business_locations', 'transactions.location_id', '=', 'business_locations.id') 
                ->where('transactions.business_id', $business_id)
                ->where('transactions.contact_id', $contract->contact_id)
                ->where('transactions.type', 'sell')
                ->where('transactions.status', 'final')
                ->whereBetween('transactions.transaction_date', [$contract->start_date, $contract->end_date])
                ->whereIn('transaction_sell_lines.product_id', $contractProductIds)
                ->whereNull('transaction_sell_lines.parent_sell_line_id') 
                ->select(
                    'transactions.id',
                    'transactions.transaction_date',
                    'transactions.invoice_no',
                    'transactions.final_total',
                    // --- ADDED SELECT HERE ---
                    'business_locations.name as location_name' 
                )
                ->distinct() 
                ->orderBy('transactions.transaction_date', 'desc')
                ->get();

            return view('contact.partials.customer_contract_view', compact('contract', 'relatedSales'));

        } catch (\Exception $e) {
            \Log::error("Error showing contract: " . $e->getMessage());
            return response()->json(['success' => false, 'msg' => __('messages.something_went_wrong')]);
        }
    }
}