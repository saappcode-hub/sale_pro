<div class="modal-dialog" role="document">
    <div class="modal-content">

        {!! Form::open(['url' => action([App\Http\Controllers\CurrencyController::class, 'store']), 'method' => 'post', 'id' => 'currency_add_form' ]) !!}

        <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title">@lang( 'Add Currency' )</h4>
        </div>

        <div class="modal-body">
            <div class="row">
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('country', __( 'country' ) . ':*') !!}
                        {!! Form::text('country', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'country' ) ]); !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('currency', __( 'currency' ) . ':*') !!}
                        {!! Form::text('currency', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'currency' ) ]); !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('code', __( 'code' ) . ':*') !!}
                        {!! Form::text('code', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'code' ) ]); !!}
                    </div>
                </div>
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('symbol', __( 'symbol' ) . ':*') !!}
                        {!! Form::text('symbol', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'symbol' ) ]); !!}
                    </div>
                </div>
               
                <div class="col-sm-12">
                    <div class="form-group">
                        {!! Form::label('exchange_rate', __( 'exchange_rate' ) . ':*') !!}
                        {!! Form::text('exchange_rate', null, ['class' => 'form-control', 'required', 'placeholder' => __( 'exchange_rate' ) ]); !!}
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">@lang( 'messages.save' )</button>
                <button type="button" class="btn btn-default" data-dismiss="modal">@lang( 'messages.close' )</button>
            </div>

            {!! Form::close() !!}

        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->