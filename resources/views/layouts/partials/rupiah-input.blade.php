<script>
(function () {
    const inputs = Array.from(document.querySelectorAll('[data-rupiah]'));

    function digitsOnly(value) {
        return String(value == null ? '' : value).replace(/\D/g, '');
    }

    function formatRupiah(value) {
        return digitsOnly(value).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    inputs.forEach(function (input) {
        input.value = formatRupiah(input.value);
        input.addEventListener('input', function () {
            input.value = formatRupiah(input.value);
        });
        input.form?.addEventListener('submit', function () {
            input.value = digitsOnly(input.value);
        });
    });
})();
</script>
