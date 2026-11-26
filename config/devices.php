<?php

return [
    'listener' => [
        'host' => env('DEVICE_LISTENER_HOST', '0.0.0.0'),
        'lab_port' => (int) env('DEVICE_LAB_PORT', 2575),
        'radiology_port' => (int) env('DEVICE_RADIOLOGY_PORT', 2576),
        'dicom_port' => (int) env('DEVICE_DICOM_PORT', 2577),
        'idle_timeout' => (int) env('DEVICE_IDLE_TIMEOUT', 300),
    ],

    'dicom' => [
        'ae_title' => env('DEVICE_DICOM_AE_TITLE', 'SCARLET'),
        'storage_disk' => env('DEVICE_DICOM_DISK', 'local'),
        'max_pdu' => (int) env('DEVICE_DICOM_MAX_PDU', 16384),
    ],

    // Shared secret for devices that push results over HTTP instead of a TCP socket.
    'ingest_token' => env('DEVICE_INGEST_TOKEN', 'scarlet-device-token'),

    // Results imported from devices are always left for explicit human approval.
    'auto_approve' => false,
];
