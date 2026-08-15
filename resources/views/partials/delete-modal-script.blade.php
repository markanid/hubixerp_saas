
<script>
document.addEventListener("DOMContentLoaded", function () {
    let deleteUrl = '';

    document.addEventListener('click', function (event) {
        const deleteBtn = event.target.closest('.delete-btn');
        if (deleteBtn) {
            event.preventDefault();
            deleteUrl = deleteBtn.getAttribute('data-url');
            $('#delete-confirmation-modal').modal('show');
        }
    });

    document.getElementById('confirm-delete-btn').addEventListener('click', function () {
        window.location.href = deleteUrl;
    });
});
</script>