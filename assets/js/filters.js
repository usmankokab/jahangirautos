// Filter form handling
document.addEventListener('DOMContentLoaded', function() {
    // Handle date inputs
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        input.addEventListener('change', function() {
            if (this.form) {
                this.form.submit();
            }
        });
    });

    // Handle select filters
    const filterSelects = document.querySelectorAll('select.filter-select');
    filterSelects.forEach(select => {
        select.addEventListener('change', function() {
            if (this.form) {
                this.form.submit();
            }
        });
    });

    // Reset filters
    const resetButtons = document.querySelectorAll('.reset-filters');
    resetButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            if (this.form) {
                const inputs = this.form.querySelectorAll('input, select');
                inputs.forEach(input => {
                    input.value = '';
                });
                this.form.submit();
            }
        });
    });
});
