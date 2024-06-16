<!-- resources/views/interviewer-rotation.blade.php -->
<html>
<head>
    <title>Interviewer-Rotationsalgorithmus</title>
    <!-- Bootstrap CSS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .slider-row {
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            height: auto; /* Set height to auto for dynamic text height */
        }
        .slider-row .col {
            display: flex;
            flex-direction: column;
            margin-bottom: 10px;
            width: 25%; /* Each slider takes 25% of the row width */
        }
        .slider-row .col input[type="range"] {
            width: 100%; /* Ensure sliders have consistent width */
        }
        .slider-row .col input[type="number"] {
            width: 100px; /* Adjusted width */
        }
        .slider-row .col input[type="range"] {
            width: calc(100% - 20px); /* Adjusted width for slider */
        }
        .slider-row label {
            width: 220px; /* Fixed width for labels */
            white-space: nowrap; /* Prevent text wrapping */
            overflow: hidden; /* Hide overflow text */
            text-overflow: ellipsis; /* Show ellipsis for long text */
        }
    </style>
</head>
<body>

<div class="container mt-5">
    <h1>Interviewer-Rotationsalgorithmus</h1>


    <!-- Upload Interviewers and Upload applicants Sections -->
    <div class="row justify-content-center">
        <div class="col-md">
            <form action="{{ route('upload', 'interviewers') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <h3 for="interviewers">Upload Interviewers</h3>
                    <input type="file" class="form-control-file" name="interviewers" id="interviewers">
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
                <!-- Download Template Button -->
                <a href="{{ route('downloadTemplate', 'interviewers') }}" class="btn btn-secondary">Download Template</a>
            </form>
        </div>
        <div class="col-md">
            <form action="{{ route('upload', 'applicants') }}" method="post" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <h3 for="applicants">Upload Applicants</h3>
                    <input type="file" class="form-control-file" name="applicants" id="applicants">
                </div>
                <button type="submit" class="btn btn-primary">Upload</button>
                <a href="{{ route('downloadTemplate', 'applicants') }}" class="btn btn-secondary">Download Template</a>

            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- Sliders Section -->
    <div class="mt-5">
        <h2>Bonusse + / Abzüge -</h2>
        <div class="row slider-row">
            @php
                //mind 1. weiblicher,
            //mind 1. männlicher
            //Techie bei Techie
            // abzüge dafü, dass 2 Techies interviewen
            // gleicher Studiengang, wie Interviewee
            // abzüge 2 mal gleicher Studiengang
            // abzüge 3 mal gleicher Studiengang
            // abzüge pro Interview Interviewer haben schon mit einander Interviewed
            // prio von Unerfahrenen und Semi Erfahrenen, dass sie möglichst schnell erfahren werden
            // abzüge pro Protokoll zu viel
            // abzüge pro 2Interview zu viel
            // abzüge pro 1Interview zu viel
            // Möglichst viele an einem Tag
            // Abzüge erstes Interview an einem Tag
            // Alle Möglichst gleich viele Interviews
            // Wenn man weniger Tage kann

                    $sliderNames = [
                        "+ mind. 1 weibliche Person",
                        "+ mind. 1 männliche Person",
                        "+ Techie bei Techie",
                        "- 2 Techies Interviewer",
                        "+ Bewerber gleiches Studium",
                        "- Dopplung des Studiengangs",
                        "- Tripplung des Studiengangs",
                        "- schon zusammen Interviewed",
                        "+ Prio Semi/Unerfahren",
                        "+/- Protokoll richtige anz.",
                        "+/- 2. Interview richtige anz.",
                        "+/- 1. Interview richtige anz.",
                        "+ Interviewer verkettete Slots",
                        "- erstes Interview am Tag",
                        "+/- gleich viele Interviews",
                        "+ weniger Tage anwesend",
                    ];
            @endphp
            @for ($i = 0; $i < 16; $i += 4)
                <div class="row">
                    @for ($j = 0; $j < 4; $j++)
                        @if(isset($sliderNames[$i + $j]))
                            <div class="col">
                                <label for="slider{{$i + $j + 1}}">{{$sliderNames[$i + $j]}}</label>
                                <input type="range" class="form-control-range" id="slider{{$i + $j + 1}}" min="0" max="1000" step="1">
                                <input type="number" class="form-control-value-input" id="sliderValue{{$i + $j + 1}}" min="0" max="1000" step="1" value="">
                            </div>
                        @endif
                    @endfor
                </div>
            @endfor
        </div>
    </div>




    <h2>Slots</h2>
    <div class="row">
        <div class="col">
            <label for="anzRaeume">Anzahl Räume</label>
            <input type="number" class="form-control-slot-input" id="anzRaeume" min="0" step="1">
        </div>
        <div class="col">
            <label for="anzSlotsFreitag">Anzahl Slots Freitag</label>
            <input type="number" class="form-control-slot-input" id="anzSlotsFreitag" min="0" step="1">
        </div>
        <div class="col">
            <label for="anzSlotsSamstag">Anzahl Slots Samstag</label>
            <input type="number" class="form-control-slot-input" id="anzSlotsSamstag" min="0" step="1">
        </div>
        <div class="col">
            <label for="anzSlotsSonntag">Anzahl Slots Sonntag</label>
            <input type="number" class="form-control-slot-input" id="anzSlotsSonntag" min="0" step="1">
        </div>
    </div>

    <div class="row justify-content-center mt-4">
            <button type="submit" onclick="submitForm()" class="btn btn-primary">CalculateRotation</button>

    </div>
    <div class="row justify-content-center mt-4">
    <div id="loading-spinner" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>
    </div>

</div>

</div>
<!-- Bootstrap JS (optional, only required if you use Bootstrap JS components) -->
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>

    function constructQueryString() {
        let queryString = '';

        // Add slider values to query string
        for (let i = 1; i <= 16; i++) {
            const sliderValue = document.getElementById(`sliderValue${i}`).value;
            queryString += `sliderValue${i}=${sliderValue}&`;
        }



        // Add slot values to query string
        queryString += `anzRaeume=${document.getElementById('anzRaeume').value}&`;
        queryString += `anzSlotsFreitag=${document.getElementById('anzSlotsFreitag').value}&`;
        queryString += `anzSlotsSamstag=${document.getElementById('anzSlotsSamstag').value}&`;
        queryString += `anzSlotsSonntag=${document.getElementById('anzSlotsSonntag').value}`;

        return queryString;
    }
    function submitForm() {
        showLoadingSpinner(); // Show loading spinner before AJAX call

        const baseUrl = "{{ route('calculateRotation') }}";
        const queryString = constructQueryString();
        const urlWithParams = `${baseUrl}?${queryString}`;
        console.log('Path: ', queryString);

        // Send AJAX request
        $.ajax({
            url: urlWithParams,
            type: 'GET',
            success: function(response) {
                // Construct full URL for the CSV file
                //downloadCsv();
                const csvData = response; // Replace with actual CSV data

                // Create a Blob from the CSV data
                const blob = new Blob([csvData], { type: 'text/csv' });

                // Create a temporary URL for the Blob
                const url = window.URL.createObjectURL(blob);

                // Create an anchor element to trigger the download
                const a = document.createElement('a');
                a.href = url;
                a.download = 'RotationPlan.csv'; // Set the desired filename

                // Append the anchor to the document and trigger the click event
                document.body.appendChild(a);
                a.click();

                // Clean up by revoking the Blob URL
                window.URL.revokeObjectURL(url);
                hideLoadingSpinner(); // Hide loading spinner after response
            },
            error: function(xhr, status, error) {
                // Handle error response
                if (xhr.status === 400) {
                    // Handle 400 Bad Request error
                    alert('Error: ' + xhr.responseText); // Display error message to the user
                } else {
                    console.error('Error:', error);
                }
                hideLoadingSpinner(); // Hide loading spinner after error
            }
        });
    }


    // Function to show loading spinner
    function showLoadingSpinner() {
        document.getElementById('loading-spinner').style.display = 'block';
    }

    // Function to hide loading spinner
    function hideLoadingSpinner() {
        document.getElementById('loading-spinner').style.display = 'none';
    }



    // Associate slider value with corresponding input field
    document.addEventListener('DOMContentLoaded', function () {
        for (let i = 1; i <= 16; i++) {
            let slider = document.getElementById('slider' + i);
            let valueInput = document.getElementById('sliderValue' + i);

            slider.addEventListener('input', function () {
                valueInput.value = slider.value;
                saveValues();
            });

            valueInput.addEventListener('input', function () {
                slider.value = valueInput.value;
                saveValues();
            });
        }
    });

    // Function to save slider values to local storage and update corresponding number input field
    function saveSliderValues() {
        for (let i = 1; i <= 16; i++) {
            const slider = document.getElementById(`slider${i}`);
            const valueInput = document.getElementById(`sliderValue${i}`);
            if (slider && valueInput) {
                valueInput.value = slider.value; // Update value field
                localStorage.setItem(`slider${i}`, slider.value);
            }
        }
    }

    // Function to save slot values to local storage
    function saveSlotsValues() {
        localStorage.setItem('anzRaeume', document.getElementById('anzRaeume').value);
        localStorage.setItem('anzSlotsFreitag', document.getElementById('anzSlotsFreitag').value);
        localStorage.setItem('anzSlotsSamstag', document.getElementById('anzSlotsSamstag').value);
        localStorage.setItem('anzSlotsSonntag', document.getElementById('anzSlotsSonntag').value);
    }

    // Function to load slider values from local storage
    function loadSliderValues() {
        for (let i = 1; i <= 16; i++) {
            const slider = document.getElementById(`slider${i}`);
            const valueInput = document.getElementById(`sliderValue${i}`);
            if (slider && valueInput) {
                const savedValue = localStorage.getItem(`slider${i}`);
                if (savedValue !== null) {
                    slider.value = savedValue;
                    valueInput.value = savedValue; // Update value field
                }
            }
        }
    }

    // Function to load slot values from local storage
    function loadSlotsValues() {
        document.getElementById('anzRaeume').value = localStorage.getItem('anzRaeume');
        document.getElementById('anzSlotsFreitag').value = localStorage.getItem('anzSlotsFreitag');
        document.getElementById('anzSlotsSamstag').value = localStorage.getItem('anzSlotsSamstag');
        document.getElementById('anzSlotsSonntag').value = localStorage.getItem('anzSlotsSonntag');
    }

    // Save slider values and slot values when any input changes
    function saveValues() {
        console.log('Saving values...');
        saveSliderValues();
        saveSlotsValues();
    }

    // Call load functions when the page loads
    window.onload = function () {
        loadSliderValues();
        loadSlotsValues();
    };

    // Save slider values when the slider inputs are changed
    const sliders = document.querySelectorAll('.form-control-range');
    sliders.forEach(slider => {
        slider.addEventListener('input', saveValues);
    });

    // Save slot values when the slot inputs are changed
    const slotInputs = document.querySelectorAll('.form-control-slot-input');
    slotInputs.forEach(input => {
        input.addEventListener('input', saveValues);
    });


</script>



</body>

</html>
