function bung_bricks_form_to_webhook( $form ){

    $data = $form->get_fields();
    $formId = $data['formId'];

    // Array untuk menyimpan URL webhook untuk setiap ID formulir
    $webhook_urls = array(
        'ummpxt' => array(
            'https://hook.us1.make.com/bxwdigckytrn5dspykzw52kttvd1up3v',
            'https://app.instanwa.id/webhook/whatsapp-workflow/48682.26311.41212.171778170',
        ),
        //'zkyusz' => array('https://hook.us1.make.com/0uckqyc8wp3bu7kcd45h4i1fmxib7y0'),
        // Tambahkan URL webhook lain untuk setiap ID formulir
    );

    // Cek apakah ID formulir ada dalam array URL webhook
    if( isset( $webhook_urls[$formId] ) ){
        $errors = array();
        
        // Inisialisasi cURL untuk setiap URL webhook yang sesuai
        foreach( $webhook_urls[$formId] as $webhook_url ){
            $curl = curl_init($webhook_url);
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json'
            ));
            $result = curl_exec($curl);

            // Check if the cURL execution was successful
            if( $result === false ){
                $errors[] = 'Webhook failed for URL: ' . $webhook_url;
            } else {
                $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                if( $response_code != 200 ){
                    $errors[] = 'Webhook responded with an error for URL: ' . $webhook_url;
                }
            }

            curl_close($curl);
        }

        // Set result of action based on errors
        if( !empty($errors) ){
            $form->set_result([
                'action' => 'my_custom_action',
                'type'    => 'danger', //or danger or info
                'message' => esc_html__('Some webhooks failed: ' . implode(', ', $errors), 'bricks'),
            ]);
        } else {
            $form->set_result([
                'action' => 'my_custom_action',
                'type'    => 'success',
                'message' => esc_html__('All webhooks succeeded', 'bricks'),
            ]);
        }
    }
}

add_action( 'bricks/form/custom_action', 'bung_bricks_form_to_webhook', 10, 1 );
