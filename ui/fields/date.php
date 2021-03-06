<?php
    $date_format = array(
        'mdy' => 'mm/dd/yy',
        'dmy' => 'dd/mm/yy',
        'dmy_dash' => 'dd-mm-yy',
        'dmy_dot' => 'dd.mm.yy',
        'ymd_slash' => 'yy/mm/dd',
        'ymd_dash' => 'yy-mm-dd',
        'ymd_dot' => 'yy.mm.dd'
    );
    $time_format = array(
        'h_mm_A' => 'h:mm:ss TT',
        'h_mm_ss_A' => 'h:mm TT',
        'hh_mm_A' => 'hh:mm TT',
        'hh_mm_ss_A' => 'hh:mm:ss TT',
        'h_mma' => 'h:mmtt',
        'hh_mma' => 'hh:mmtt',
        'h_mm' => 'h:mm',
        'h_mm_ss' => 'h:mm:ss',
        'hh_mm' => 'hh:mm',
        'hh_mm_ss' => 'hh:mm:ss'
    );

    wp_enqueue_script('jquery-ui-datepicker');
    wp_enqueue_script('jquery-ui-timepicker');
    wp_enqueue_style('jquery-ui');
    wp_enqueue_style('jquery-ui-timepicker');

    $attributes = array();

    $type = 'text';

    $date_type = pods_var_raw( 'date_format_type', $options, 'date' );

    if ( 1 == $options[ 'date_html5' ] )
        $type = $date_type;

    $attributes[ 'type' ] = $type;
    $attributes[ 'tabindex' ] = 2;

    $args = array(
        'timeFormat' => $time_format[ $options[ 'date_time_format' ] ],
        'dateFormat' => $date_format[ $options[ 'date_format' ] ]
    );

    $format = PodsForm::field_method( 'date', 'format', $options );

    if ( 'datetime' == $date_type ) {
        $method = 'datetimepicker';

        $args = array(
            'timeFormat' => $time_format[ $options[ 'date_time_format' ] ],
            'dateFormat' => $date_format[ $options[ 'date_format' ] ]
        );

        if ( false !== stripos( $args[ 'timeFormat' ], 'tt' ) )
            $args[ 'ampm' ] = true;

        $html5_format = 'Y-m-d\TH:i:s';
    }
    elseif ( 'date' == $date_type ) {
        $method = 'datepicker';

        $args = array(
            'dateFormat' => $date_format[ $options[ 'date_format' ] ]
        );

        $html5_format = 'Y-m-d';
    }
    else { //if ( 'time' == $date_type ) {
        $method = 'timepicker';

        $args = array(
            'timeFormat' => $time_format[ $options[ 'date_time_format' ] ]
        );

        if ( false !== stripos( $args[ 'timeFormat' ], 'tt' ) )
            $args[ 'ampm' ] = true;

        $html5_format = '\TH:i:s';
    }

    if ( 24 == pods_var( 'date_time_type', $options, 12 ) )
        $args[ 'ampm' ] = false;

    $date = PodsForm::field_method( 'date', 'createFromFormat', $format, (string) $value );
    $date_default = PodsForm::field_method( 'date', 'createFromFormat', 'Y-m-d H:i:s', (string) $value );

    if ( 'text' != $type ) {
        $formatted_date = $value;

        if ( false !== $date )
            $value = $date->format( $html5_format );
        elseif ( false !== $date_default )
            $value = $date_default->format( $html5_format );
        elseif ( !empty( $value ) )
            $value = date_i18n( $html5_format, strtotime( (string) $value ) );
        else
            $value = date_i18n( $html5_format );
    }

    $args = apply_filters( 'pods_form_ui_field_date_args', $args, $type, $options, $attributes, $name, PodsForm::$field_type );

    $attributes[ 'value' ] = $value;

    $attributes = PodsForm::merge_attributes( $attributes, $name, PodsForm::$field_type, $options );
?>
<input<?php PodsForm::attributes( $attributes, $name, PodsForm::$field_type, $options ); ?> />
<script>
    jQuery( function () {
        var <?php echo pods_clean_name( $attributes[ 'id' ] ); ?>_args = <?php echo json_encode( $args ); ?>;

        <?php
            if ( 'text' != $type ) {
        ?>

        if ( 'undefined' == typeof pods_test_date_field_<?php echo $type; ?> ) {
        // Test whether or not the browser supports date inputs
            function pods_test_date_field_<?php echo $type; ?> () {
                var input = jQuery( '<input/>', {
                    'type' : '<?php echo $type; ?>',
                    css : {
                        position : 'absolute',
                        display : 'none'
                    }
                } );

                jQuery( 'body' ).append( input );

                var bool = input.prop( 'type' ) !== 'text';

                if ( bool ) {
                    var smile = ":)";
                    input.val( smile );

                    return (input.val() != smile);
                }
            }
        }

        if ( !pods_test_date_field_<?php echo $type; ?>() ) {
            jQuery( 'input#<?php echo $attributes[ 'id' ]; ?>' ).val( '<?php echo $formatted_date; ?>' );
            jQuery( 'input#<?php echo $attributes[ 'id' ]; ?>' ).<?php echo $method; ?>( <?php echo pods_clean_name( $attributes[ 'id' ] ); ?>_args );
        }

        <?php
            }
            else {
        ?>

        jQuery( 'input#<?php echo $attributes[ 'id' ]; ?>' ).<?php echo $method; ?>( <?php echo pods_clean_name( $attributes[ 'id' ] ); ?>_args );

        <?php
            }
        ?>
    } );
</script>
