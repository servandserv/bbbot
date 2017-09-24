<?php

namespace com\servandserv\bot\domain\service;

use \com\servandserv\data\bot\Location;

class Distance
{
    const COORDINATES_FORMAT = "WGS84";
    const MAJOR_AXIS = 6378137.0; //meters
    const MINOR_AXIS = 6356752.3142; //meters
    const ELEVATION = 500;

    /*
    $form['lat'] - latitude (широта)
    $from['lon'] - longitude (долгота)
    $from['point_elevation'] (высота точки) // == 0 if this is sea. but must be defined!
    */

    //get arrays with gps coordinates, returns earth terrestrial distance between 2 points
    public function get( Location $from, Location $to, $decart = FALSE )
    {
        if( !$decart ) {
            $true_angle_from = $this->getTrueAngle( $from );
            $true_angle_to = $this->getTrueAngle( $to );
            
            $point_radius_from = $this->getPointRadius( $from, $true_angle_from );
            $point_radius_to = $this->getPointRadius( $to, $true_angle_to );
        
            $earth_point_from_x = $point_radius_from * cos( deg2rad( $true_angle_from ) );
            $earth_point_from_y = $point_radius_from * sin( deg2rad( $true_angle_from ) );
        
            $earth_point_to_x = $point_radius_to * cos( deg2rad( $true_angle_to ) );
            $earth_point_to_y = $point_radius_to * sin( deg2rad( $true_angle_to ) );
        
            $x = ( new self() )->get(
                ( new Location() )->setLatitude( $earth_point_from_x )->setLongitude( $earth_point_from_y ),
                ( new Location() )->setLatitude( $earth_point_to_x )->setLongitude( $earth_point_to_y ),
                TRUE
            );
            $y = pi() *  (  ( $earth_point_from_x + $earth_point_to_x ) / 360 ) * ( $from->getLongitude() - $to->getLongitude() );
            
            return sqrt( pow( $x, 2 ) + pow( $y, 2 ) );
        } else {
            return sqrt( pow( ( $from->getLatitude() - $to->getLatitude() ), 2 ) + pow( ( $from->getLongitude() - $to->getLongitude() ), 2 ) );
        }
    }

    //returns degree's decimal measure, getting degree, minute and second
    private function getDecimalDegree( $deg = 0, $min = 0, $sec = 0 )
    {
        return ( $deg<0 ) ? ( -1*( abs( $deg ) + ( abs( $min ) / 60 ) + ( abs( $sec ) / 3600 ) ) ) : ( abs( $deg ) + ( abs( $min ) / 60 ) + ( abs( $sec ) / 3600 ) );
    }

    // get point, returns true angle
    private function getTrueAngle( Location $loc )
    {
        return atan( ( ( pow( self::MINOR_AXIS, 2 ) / pow( self::MAJOR_AXIS, 2 ) ) * tan( deg2rad( $loc->getLatitude() ) ) ) ) * 180 / pi(); 
    }

    //get point and true angle, returns radius of small circle (radius between meridians) 
    private function getPointRadius( Location $loc, $true_angle )
    {
        return (1 / sqrt( ( pow( cos( deg2rad( $true_angle ) ), 2) / 
            pow( self::MAJOR_AXIS, 2 ) ) + 
            ( pow( sin( deg2rad( $true_angle ) ), 2 ) / 
            pow( self::MINOR_AXIS, 2 ) ) ) ) + 
            $loc->getElevation();
    }

    private function check_lat( $lat )
    {
        if( $lat>=0 && $lat<=90 )
        {
            return "north";
        }
        elseif( $lat>=-90 && $lat<=0 )
        {
            return "south";
        }

        return false;
    }

    private function check_lon( $lon )
    {
        if( $lon>=0 && $lon<=180 )
        {
            return "east";
        }
        elseif( $lon >= -180 && $lon <= 0 )
        {
            return "west";
        }
        return false;
    } 
}