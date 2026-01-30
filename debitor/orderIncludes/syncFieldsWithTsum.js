document.addEventListener('DOMContentLoaded', function () {
    const felt2 = document.querySelector('input[name="felt_2"]');
    const felt4 = document.querySelector('input[name="felt_4"]');
    const ordresum = document.querySelector('input[name="ordresum"]');

    if (!felt2 || !ordresum || !ordresum.value) {
        return;
    }

    const felt4Value = felt4 && felt4.value
        ? parseFloat(felt4.value.replace('.', '').replace(',', '.'))
        : 0;

    const ordresumValue = parseFloat(ordresum.value);

    if (isNaN(ordresumValue) || ordresumValue === 0) {
        return;
    }


    const resultValue = ordresumValue - (isNaN(felt4Value) ? 0 : felt4Value);

    // Format to Danish format (supports negative numbers)
    const danishFormatter = new Intl.NumberFormat('da-DK', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });

    const resultFormatted = danishFormatter.format(resultValue);


    const felt2Value = felt2.value
        ? parseFloat(felt2.value.replace('.', '').replace(',', '.'))
        : null;

    if (felt2.value === '' || felt2Value !== resultValue) {
        console.log(`Setting felt_2 value to: ${resultFormatted}`);
        felt2.value = resultFormatted;
    }
});
