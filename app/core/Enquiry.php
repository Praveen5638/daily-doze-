<?php
declare(strict_types=1);

final class Enquiry
{
    public static function create(string $type, array $data): array
    {
        $type = in_array($type, ['wholesale', 'export'], true) ? $type : 'wholesale';
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = preg_replace('/[^0-9+]/', '', (string) ($data['phone'] ?? '')) ?? '';
        $country = trim((string) ($data['country'] ?? 'India'));
        $message = trim((string) ($data['message'] ?? ''));

        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'message' => 'Please enter your full name.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address.'];
        }
        if (strlen(preg_replace('/\D/', '', $phone) ?? '') < 7) {
            return ['ok' => false, 'message' => 'Please enter a valid phone number.'];
        }
        if ($country === '') {
            return ['ok' => false, 'message' => 'Please enter your country.'];
        }
        if (mb_strlen($message) > 3000) {
            return ['ok' => false, 'message' => 'Please keep your message under 3000 characters.'];
        }
        $buyerType = $type === 'export' ? 'export' : (string) ($data['buyer_type'] ?? 'wholesale');
        if (!in_array($buyerType, ['retail', 'wholesale', 'export'], true)) {
            $buyerType = $type === 'export' ? 'export' : 'wholesale';
        }

        $reference = unique_code('ENQ', 'enquiries', 'reference');
        $id = Database::insert('enquiries', [
            'reference' => $reference,
            'type' => $type,
            'name' => $name,
            'company' => trim((string) ($data['company'] ?? '')),
            'email' => $email,
            'phone' => $phone,
            'country' => mb_substr($country, 0, 80),
            'city' => trim((string) ($data['city'] ?? '')),
            'product_interest' => mb_substr(trim((string) ($data['product_interest'] ?? '')), 0, 255),
            'quantity' => mb_substr(trim((string) ($data['quantity'] ?? '')), 0, 120),
            'packaging' => mb_substr(trim((string) ($data['packaging'] ?? '')), 0, 190),
            'buyer_type' => $buyerType,
            'destination_port' => mb_substr(trim((string) ($data['destination_port'] ?? '')), 0, 190),
            'message' => $message !== '' ? $message : null,
            'status' => 'new',
        ]);

        if (trim((string) ($data['product_interest'] ?? '')) !== '') {
            Database::insert('enquiry_items', [
                'enquiry_id' => $id,
                'product_name' => mb_substr(trim((string) $data['product_interest']), 0, 190),
                'quantity' => mb_substr(trim((string) ($data['quantity'] ?? '')), 0, 120),
                'packaging' => mb_substr(trim((string) ($data['packaging'] ?? '')), 0, 190),
            ]);
        }

        Notifications::admin(
            $type . '_enquiry',
            ucfirst($type) . ' enquiry from ' . $name,
            trim($country . ' · ' . ($data['product_interest'] ?? '') . ' · ' . ($data['quantity'] ?? '')),
            'enquiries.php'
        );
        Mailer::send(
            $email,
            'We received your ' . ($type === 'export' ? 'export' : 'wholesale') . ' enquiry — ' . $reference,
            email_wrap(
                'Thank you for your enquiry',
                '<p>Hello ' . e($name) . ',</p><p>We received your enquiry (reference <strong>' . e($reference) . '</strong>). Our team will review it and get back to you with availability and a quotation.</p>'
                . '<p><strong>Enquiry summary</strong><br>Product: ' . e((string) ($data['product_interest'] ?? '—')) . '<br>Quantity: ' . e((string) ($data['quantity'] ?? '—')) . '<br>Country: ' . e($country) . '</p>'
                . '<p>This is not an order confirmation. Pricing and export documentation will be confirmed by our team.</p>'
            )
        );
        Mailer::sendAdmin(
            'New ' . $type . ' enquiry ' . $reference,
            email_wrap(
                'New ' . $type . ' enquiry',
                '<p><strong>' . e($name) . '</strong> — ' . e($email) . ' — ' . e($phone) . '</p>'
                . '<p>' . e($country) . ' · ' . e((string) ($data['company'] ?? '')) . '</p>'
                . '<p>Product: ' . e((string) ($data['product_interest'] ?? '')) . '<br>Quantity: ' . e((string) ($data['quantity'] ?? '')) . '<br>Packaging: ' . e((string) ($data['packaging'] ?? '')) . '</p>'
                . '<p>' . nl2br(e($message)) . '</p>',
                ['url' => url('admin/enquiries.php'), 'label' => 'Open enquiries']
            )
        );

        return ['ok' => true, 'message' => 'Thank you. Your enquiry has been sent — reference ' . $reference . '.', 'reference' => $reference];
    }

    public static function contact(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $phone = preg_replace('/[^0-9+]/', '', (string) ($data['phone'] ?? '')) ?? '';
        $subject = trim((string) ($data['subject'] ?? ''));
        $message = trim((string) ($data['message'] ?? ''));

        if ($name === '' || mb_strlen($name) > 120) {
            return ['ok' => false, 'message' => 'Please enter your name.'];
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'message' => 'Please enter a valid email address.'];
        }
        if ($subject === '') {
            $subject = 'Website enquiry';
        }
        if ($message === '' || mb_strlen($message) > 3000) {
            return ['ok' => false, 'message' => 'Please write a message (up to 3000 characters).'];
        }

        Database::insert('contact_messages', [
            'name' => $name,
            'email' => $email,
            'phone' => $phone !== '' ? $phone : null,
            'subject' => mb_substr($subject, 0, 190),
            'message' => $message,
        ]);
        Notifications::admin('contact_message', 'New message: ' . $subject, $name . ' · ' . $email, 'messages.php');
        Mailer::sendAdmin(
            'Contact message: ' . $subject,
            email_wrap('New contact message', '<p><strong>' . e($name) . '</strong><br>' . e($email) . ' · ' . e($phone) . '</p><p>' . nl2br(e($message)) . '</p>')
        );
        Mailer::send(
            $email,
            'We received your message — ' . setting('site_name', 'Daily Dose Export'),
            email_wrap('Thank you for contacting us', '<p>Hello ' . e($name) . ',</p><p>Our team has received your message and will respond as soon as possible.</p><p><em>Your message:</em><br>' . nl2br(e($message)) . '</p>')
        );
        return ['ok' => true, 'message' => 'Thank you. Your message has been sent.'];
    }
}
