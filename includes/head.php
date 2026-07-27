<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="assets/runitc.png">

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

<style>
    body {
        opacity: 0;
        transform: scale(0.98);
        transition: opacity 0.4s ease-out, transform 0.4s ease-out;
        background-color: #f9fafb;
    }

    body.page-loaded {
        opacity: 1;
        transform: scale(1);
    }
</style>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        setTimeout(() => {
            document.body.classList.add('page-loaded');
        }, 50);
    });
</script>