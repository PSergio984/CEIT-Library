<div>
    {{-- Attendance QR Component --}}
    <div class="flex flex-col items-center justify-center py-4">
        <div class="mb-4 font-semibold text-lg">Attendance QR</div>

        <img src="{{ $qrCodeDataUri }}" alt="Attendance QR Code" class="rounded shadow mb-4"/>

        <div class="mb-4 text-sm text-muted-foreground">Scan this QR code to log your attendance.</div>

        <a href="{{ $qrCodeDataUri }}" download="attendance-qrcode.png"
           class="inline-flex items-center px-4 py-2 bg-primary text-primary-foreground rounded-lg hover:bg-primary/90 transition-colors">
            <x-mary-icon name="o-arrow-down-tray" label="Download QR Code"/>
        </a>
    </div>
</div>
