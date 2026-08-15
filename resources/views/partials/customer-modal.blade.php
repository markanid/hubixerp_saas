<!-- Customer Modal -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" role="dialog" aria-labelledby="addCustomerModalLabel" aria-hidden="true" style="display: none;">
  <div class="modal-dialog modal-sm" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="addCustomerModalLabel">Create New Customer</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="customerForm">
        <div class="modal-body">
            <div class="form-group">
                <label for="new_customer_name">Customer Name</label>
                <input type="text" class="form-control" id="new_customer_name">
                <span class="text-danger error-name"></span>
            </div>
            <div class="form-group">
                <label for="new_customer_phone">Phone</label>
                <input type="text" class="form-control" id="new_customer_phone">
                <span class="text-danger error-phone"></span>
            </div>
        </div>
        <div class="modal-footer d-flex justify-content-center">
            <button type="submit" class="btn btn-primary  btn-flat"><i class="fas fa-save"></i> Save</button>
            <button type="button" class="btn btn-secondary  btn-flat" data-dismiss="modal"><i class="fas fa-undo-alt"></i> Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>