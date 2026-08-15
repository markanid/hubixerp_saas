<div class="modal fade" id="mrpStockLotModal" tabindex="-1" role="dialog" aria-labelledby="mrpStockLotModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="mrpStockLotModalLabel">
                    <i class="fas fa-boxes mr-1"></i> Select MRP Stock
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <div id="mrp_lot_modal_message" class="alert alert-info m-3" style="display:none"></div>
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0" id="mrp_lot_modal_table">
                        <thead class="thead-light">
                            <tr>
                                <th class="text-center">MRP</th>
                                <th class="text-center">Sale Price</th>
                                <th class="text-center">Stock Qty</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-flat" data-dismiss="modal">
                    <i class="fas fa-times mr-1"></i> Cancel
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    #mrpStockLotModal .modal-dialog {
        width: calc(100% - 1rem);
        max-width: 440px;
        margin: .5rem auto;
    }

    #mrp_lot_modal_table {
        table-layout: fixed;
    }

    #mrp_lot_modal_table th,
    #mrp_lot_modal_table td {
        width: 33.333%;
        text-align: center !important;
    }

    #mrp_lot_modal_table .select-mrp-lot:focus {
        background-color: #d9edf7;
        outline: 3px solid #007bff;
        outline-offset: -3px;
    }

    @media (min-width: 576px) {
        #mrpStockLotModal .modal-dialog {
            margin: 1.75rem auto;
        }
    }
</style>
