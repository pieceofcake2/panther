var button = document.getElementById('load-button');
if (button) {
    button.addEventListener('click', function () {
        var spinner = document.getElementById('spinner');
        spinner.style.display = 'block';
        button.setAttribute('data-state', 'loading');
        setTimeout(function () {
            fetch('/contacts/ajax')
                .then(function (response) { return response.text(); })
                .then(function (text) {
                    document.getElementById('ajax-result').textContent = text;
                    spinner.style.display = 'none';
                    button.setAttribute('data-state', 'done');
                    document.getElementById('next-button').disabled = false;
                });
        }, 300);
    });
}
