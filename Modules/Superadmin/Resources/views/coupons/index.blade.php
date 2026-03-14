@extends('layouts.app')
@section('title', __('superadmin::lang.superadmin') . ' | Coupons')

@section('content')
    @include('superadmin::layouts.nav')
    <!-- Content Header (Page header) -->
    <section class="content-header">
        <h1> @lang('superadmin::lang.all_coupon')
        </h1>
    </section>

    <!-- Main content -->
    <section class="content">
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">&nbsp;</h3>
                <div class="box-tools">
                    <a href="{{ action([\Modules\Superadmin\Http\Controllers\CouponController::class, 'create']) }}"
                        class="btn btn-block btn-primary">
                        <i class="fa fa-plus"></i> @lang('messages.add')</a>
                </div>
            </div>
            <div class="box-body">
                @can('superadmin')
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped" id="superadmin_Coupons_table">
                            <thead>
                                <tr>
                                    <th>
                                        @lang('superadmin::lang.coupon_code')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.discount_type')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.discount')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.expiry_date')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.applied_on_packages')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.applied_on_business')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.status')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.created_at')
                                    </th>
                                    <th>
                                        @lang('superadmin::lang.action')
                                    </th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                @endcan
            </div>
        </div>

    </section>
    <!-- /.content -->

@endsection

@section('javascript')

    <script type="text/javascript">
        $(document).ready(function() {
            superadmin_business_table = $('#superadmin_Coupons_table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: "{{ action([\Modules\Superadmin\Http\Controllers\CouponController::class, 'index']) }}",
                },
                aaSorting: [
                    [6, 'desc']
                ],
                columns: [{
                        data: 'coupon_code',
                        name: 'coupons.coupon_code'
                    },
                    {
                        data: 'discount_type',
                        name: 'coupons.discount_type'
                    },
                    {
                        data: 'discount',
                        name: 'coupons.discount'
                    },
                    {
                        data: 'expiry_date',
                        name: 'coupons.expiry_date'
                    },
                    {
                        data: 'applied_on_packages',
                        name: 'coupons.applied_on_packages'
                    },
                    {
                        data: 'applied_on_business',
                        name: 'coupons.applied_on_business'
                    },
                    {
                        data: 'is_active',
                        name: 'coupons.is_active'
                    },
                    {
                        data: 'created_at',
                        name: 'coupons.created_at'
                    },
                    {
                        data: 'action',
                        name: 'coupons.action'
                    },
                ]
            });

            $(document).on('click', 'a.delete_coupon_confirmation', function(e) {
                e.preventDefault();
                swal({
                    title: LANG.sure,
                    text: "Once deleted, you will not be able to recover this Coupon !",
                    icon: "warning",
                    buttons: true,
                    dangerMode: true,
                }).then((confirmed) => {
                    if (confirmed) {
                        window.location.href = $(this).attr('href');
                    }
                });
            });
        });
    </script>

@endsection
