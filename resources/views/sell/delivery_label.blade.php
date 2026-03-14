<!DOCTYPE html>
<html lang="en">
<head>
    <style type="text/css">
        @page {
            width: 80mm;
            height: 50mm;
            margin: 0px; /* Remove default margins */
            padding: 0px;
        }

        body, html {
            margin: 0px;
            padding: 0px;
            width: 80mm;
            height: 50mm;
            font-family: Arial, sans-serif;
            font-size: medium; /* Set font size to medium */
            display: flex;
            flex-direction: column; /* Stack the sections vertically */
            justify-content: center; /* Center content vertically */
        }

        .label {
            width: 80mm;
            height: 50mm;
            display: flex;
            flex-direction: column; /* Stack the sections vertically */
            justify-content: space-between; /* Distribute space between elements */
            border: 1px solid white;
            border-radius: 5px;
            padding: 0px; /* Ensure no padding on label */
            margin: 0px;
        }

        .sender {
            display: flex; /* Aligns the text and barcode side by side */
            justify-content: space-between; /* Ensures text and barcode do not overlap */
            align-items: center; /* Align vertically */
            height: 20mm; /* Height for sender section */
            width: 80mm;
            position: relative; /* Needed for the vertical line */
        }

        .senderText {
            width: 35mm; /* Maximum width of the sender text area to prevent overlap */
            word-wrap: break-word; /* Wrap text that is too long to fit on one line */
            overflow: hidden; /* Hide overflow */
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: flex-start;
            text-align: left;
            padding-left: 5px; /* Added padding on the left */
        }

        .bold {
            font-weight: bold; /* Bold style for specific text */
        }

        .verticalLine {
            width: 2mm;
            position: absolute; /* Position it within .sender */
            left: 45%; /* Center the line */
            top: 0;
            bottom: 0;
            border-left: 1px solid black; /* The vertical line */
            height: auto; /* Full height */
        }

        .barcode {
            width: 43mm; /* Set the width of the barcode container */
            height: 100%; /* Take the full height of the parent */
            display: flex;
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            margin: 0; /* Remove any default margin */
            padding: 0; /* Remove any default padding */
        }

        .barcode img {
            width: 65%; /* The barcode image will fill the 43mm width exactly */
            height: auto; /* Maintain the aspect ratio of the image */
            justify-content: center;
            align-items: center;
        }

        .receiver {
            height: auto; /* Flexible height to accommodate content */
            width: 80mm;
            min-height: 30mm; /* Ensures it has a minimum height if content is less */
            text-align: left;
            display: flex;
            flex-direction: column;
            justify-content: flex-start; /* Align content to the top */
            align-items: flex-start; /* Align items to the left */
            padding: 5px; /* Padding around content */
            margin-top: 10px; /* Margin above for separation */
        }

        p {
            margin: 0.5mm 0; /* Reduced margin around paragraphs */
            word-wrap: break-word; /* Allows long words to be broken and wrapped */
            overflow-wrap: break-word; /* Ensures content wraps properly */
            word-break: break-word; /* Breaks long words if necessary */
            white-space: normal; /* Ensures text wraps automatically */
            line-height: 12px; /* Line height for better readability */
            font-size: 10px; /* Set font size to medium */
            font-weight: 300;
        }

        hr {
            width: calc(100% + 10px); /* Extending full width and compensating for any parent padding */
            border: none;
            border-top: 1px solid black;
            margin: 0;
            padding: 0;
        }

        .loading-indicator {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background-color: rgba(0, 0, 0, 0.75);
            color: #fff;
            padding: 10px 20px;
            border-radius: 5px;
            z-index: 10000;
            font-size: 16px;
            text-align: center;
        }

        @media print {
            body, html {
                max-width: 80mm;
                height: 50mm;
                font-size: 12px;
                margin: 0mm !important;
                padding: 0mm !important;
            }
            .label {
                border: none;
                border-radius: 0;
                margin: 0mm !important;
                padding: 0mm !important;
            }
            .barcode {
                width: 43mm !important;
                justify-content: center !important;
                align-items: center !important;
                padding: 5px;
            }
            .barcode img {
                width: 65% !important; /* Make barcode image fully responsive in print */
                height: auto !important; /* Maintain aspect ratio */
                justify-content: center !important;
                align-items: center !important;
            }
        }
    </style>
</head>
<body>
   <div class="label">
        <div class="sender">
            <div class="senderText">
                <p><span class="bold">Sender:</span> {{$transaction->location->name}}</p>
                <p><span class="bold">Mobile:</span> {{$transaction->location->mobile}}</p>
            </div>
            <div class="verticalLine"></div>
            <div class="barcode">
                @if(!empty($barcodeBase64))
                    <img src="data:image/png;base64,{{ $barcodeBase64 }}" alt="Barcode Image">
                @else
                    <p>Barcode not available</p>
                @endif
            </div>
        </div>
        <hr>
        <div class="receiver">
            <p><span class="bold">Receiver:</span> {{$transaction->contact->name}}</p>
            <p><span class="bold">Mobile:</span> {{$transaction->contact->mobile}}</p>
            <p><span class="bold">Address:</span> {{$transaction->contact->address_line_1}}</p>
            <p><span class="bold">Delivered by:</span> {{$transaction->delivery_person_user->username ?? ""}}</p>
        </div>
    </div>
</body>
</html>
