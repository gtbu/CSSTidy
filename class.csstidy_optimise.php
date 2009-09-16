<?php
/**
 * CSSTidy - CSS Parser and Optimiser
 *
 * CSS Optimising Class
 * This class optimises CSS data generated by csstidy.
 *
 * Copyright 2005, 2006, 2007 Florian Schmitz
 *
 * This file is part of CSSTidy.
 *
 *   CSSTidy is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU Lesser General Public License as published by
 *   the Free Software Foundation; either version 2.1 of the License, or
 *   (at your option) any later version.
 *
 *   CSSTidy is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU Lesser General Public License for more details.
 * 
 *   You should have received a copy of the GNU Lesser General Public License
 *   along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license http://opensource.org/licenses/lgpl-license.php GNU Lesser General Public License
 * @package csstidy
 * @author Florian Schmitz (floele at gmail dot com) 2005-2007
 * @author Brett Zamir (brettz9 at yahoo dot com) 2007
 */

/**
 * CSS Optimising Class
 *
 * This class optimises CSS data generated by csstidy.
 *
 * @package csstidy
 * @author Florian Schmitz (floele at gmail dot com) 2005-2006
 * @version 1.0
 */

class csstidy_optimise
{
    /**
     * Constructor
     * @param array $css contains the class csstidy
     * @access private
     * @version 1.0
     */
    function csstidy_optimise(&$css)
    {
        $this->parser    =& $css;
        $this->css       =& $css->css;
        $this->sub_value =& $css->sub_value;
        $this->at        =& $css->at;
        $this->selector  =& $css->selector;
        $this->property  =& $css->property;
        $this->value     =& $css->value;
    }

    /**
     * Optimises $css after parsing
     * @access public
     * @version 1.0
     */
    function postparse()
    {
        if ($this->parser->get_cfg('preserve_css')) {
            return;
        }

        if ($this->parser->get_cfg('merge_selectors') === 2)
        {
            foreach ($this->css as $medium => $value)
            {
                $this->merge_selectors($this->css[$medium]);
            }
        }
        
        if ($this->parser->get_cfg('discard_invalid_selectors')) {
            foreach ($this->css as $medium => $value)
            {
                $this->discard_invalid_selectors($this->css[$medium]);
            }
        }

        if ($this->parser->get_cfg('optimise_shorthands') > 0)
        {
            foreach ($this->css as $medium => $value)
            {
                foreach ($value as $selector => $value1)
                {
                    $this->css[$medium][$selector] = csstidy_optimise::merge_4value_shorthands($this->css[$medium][$selector]);

                    if ($this->parser->get_cfg('optimise_shorthands') < 2) {
                        continue;
                    }

                    $this->css[$medium][$selector] = csstidy_optimise::merge_bg($this->css[$medium][$selector]);
                    if (empty($this->css[$medium][$selector])) {
                        unset($this->css[$medium][$selector]);
                    }
                }
            }
        }
    }

    /**
     * Optimises values
     * @access public
     * @version 1.0
     */
    function value()
    {
        $shorthands =& $GLOBALS['csstidy']['shorthands'];

        // optimise shorthand properties
        if(isset($shorthands[$this->property]))
        {
            $temp = csstidy_optimise::shorthand($this->value); // FIXME - move
            if($temp != $this->value)
            {
                $this->parser->log('Optimised shorthand notation ('.$this->property.'): Changed "'.$this->value.'" to "'.$temp.'"','Information');
            }
            $this->value = $temp;
        }

        // Remove whitespace at ! important
        if($this->value != $this->compress_important($this->value))
        {
            $this->parser->log('Optimised !important','Information');
        }
    }

    /**
     * Optimises shorthands
     * @access public
     * @version 1.0
     */
    function shorthands()
    {
        $shorthands =& $GLOBALS['csstidy']['shorthands'];

        if(!$this->parser->get_cfg('optimise_shorthands') || $this->parser->get_cfg('preserve_css')) {
            return;
        }

        if($this->property === 'background' && $this->parser->get_cfg('optimise_shorthands') > 1)
        {
            unset($this->css[$this->at][$this->selector]['background']);
            $this->parser->merge_css_blocks($this->at,$this->selector,csstidy_optimise::dissolve_short_bg($this->value));
        }
        if(isset($shorthands[$this->property]))
        {
            $this->parser->merge_css_blocks($this->at,$this->selector,csstidy_optimise::dissolve_4value_shorthands($this->property,$this->value));
            if(is_array($shorthands[$this->property]))
            {
                unset($this->css[$this->at][$this->selector][$this->property]);
            }
        }
    }

    /**
     * Optimises a sub-value
     * @access public
     * @version 1.0
     */
    function subvalue()
    {
        $replace_colors =& $GLOBALS['csstidy']['replace_colors'];

        $this->sub_value = trim($this->sub_value);
        if($this->sub_value == '') // caution : '0'
        {
            return;
        }

        $important = '';
        if(csstidy::is_important($this->sub_value))
        {
            $important = '!important';
        }
        $this->sub_value = csstidy::gvw_important($this->sub_value);

        // Compress font-weight
        if($this->property === 'font-weight' && $this->parser->get_cfg('compress_font-weight'))
        {
            if($this->sub_value === 'bold')
            {
                $this->sub_value = '700';
                $this->parser->log('Optimised font-weight: Changed "bold" to "700"','Information');
            }
            else if($this->sub_value === 'normal')
            {
                $this->sub_value = '400';
                $this->parser->log('Optimised font-weight: Changed "normal" to "400"','Information');
            }
        }

        $temp = $this->compress_numbers($this->sub_value);
        if(strcasecmp($temp, $this->sub_value) !== 0)
        {
            if(strlen($temp) > strlen($this->sub_value)) {
                $this->parser->log('Fixed invalid number: Changed "'.$this->sub_value.'" to "'.$temp.'"','Warning');
            } else {
                $this->parser->log('Optimised number: Changed "'.$this->sub_value.'" to "'.$temp.'"','Information');
            }
            $this->sub_value = $temp;
        }
        if($this->parser->get_cfg('compress_colors'))
        {
            $temp = $this->cut_color($this->sub_value);
            if($temp !== $this->sub_value)
            {
                if(isset($replace_colors[$this->sub_value])) {
                    $this->parser->log('Fixed invalid color name: Changed "'.$this->sub_value.'" to "'.$temp.'"','Warning');
                } else {
                    $this->parser->log('Optimised color: Changed "'.$this->sub_value.'" to "'.$temp.'"','Information');
                }
                $this->sub_value = $temp;
            }
        }
        $this->sub_value .= $important;
    }

    /**
     * Compresses shorthand values. Example: margin:1px 1px 1px 1px -> margin:1px
     * @param string $value
     * @access public
     * @return string
     * @version 1.0
     */
    function shorthand($value)
    {
        $important = '';
        if(csstidy::is_important($value))
        {
            $values = csstidy::gvw_important($value);
            $important = '!important';
        }
        else $values = $value;

        $values = explode(' ',$values);
        switch(count($values))
        {
            case 4:
            if($values[0] == $values[1] && $values[0] == $values[2] && $values[0] == $values[3])
            {
                return $values[0].$important;
            }
            elseif($values[1] == $values[3] && $values[0] == $values[2])
            {
                return $values[0].' '.$values[1].$important;
            }
            elseif($values[1] == $values[3])
            {
                return $values[0].' '.$values[1].' '.$values[2].$important;
            }
            break;

            case 3:
            if($values[0] == $values[1] && $values[0] == $values[2])
            {
                return $values[0].$important;
            }
            elseif($values[0] == $values[2])
            {
                return $values[0].' '.$values[1].$important;
            }
            break;

            case 2:
            if($values[0] == $values[1])
            {
                return $values[0].$important;
            }
            break;
        }

        return $value;
    }

    /**
     * Removes unnecessary whitespace in ! important
     * @param string $string
     * @return string
     * @access public
     * @version 1.1
     */
    function compress_important(&$string)
    {
        if(csstidy::is_important($string))
        {
            $string = csstidy::gvw_important($string) . '!important';
        }
        return $string;
    }

    /**
     * Color compression function. Converts all rgb() values to #-values and uses the short-form if possible. Also replaces 4 color names by #-values.
     * @param string $color
     * @return string
     * @version 1.1
     */
    function cut_color($color)
    {
        $replace_colors =& $GLOBALS['csstidy']['replace_colors'];

        // rgb(0,0,0) -> #000000 (or #000 in this case later)
        if(strtolower(substr($color,0,4)) === 'rgb(')
        {
            $color_tmp = substr($color,4,strlen($color)-5);
            $color_tmp = explode(',',$color_tmp);
            for ( $i = 0; $i < count($color_tmp); $i++ )
            {
                $color_tmp[$i] = trim ($color_tmp[$i]);
                if(substr($color_tmp[$i],-1) === '%')
                {
                    $color_tmp[$i] = round((255*$color_tmp[$i])/100);
                }
                if($color_tmp[$i]>255) $color_tmp[$i] = 255;
            }
            $color = '#';
            for ($i = 0; $i < 3; $i++ )
            {
                if($color_tmp[$i]<16) {
                    $color .= '0' . dechex($color_tmp[$i]);
                } else {
                    $color .= dechex($color_tmp[$i]);
                }
            }
        }

        // Fix bad color names
        if(isset($replace_colors[strtolower($color)]))
        {
            $color = $replace_colors[strtolower($color)];
        }

        // #aabbcc -> #abc
        if(strlen($color) == 7)
        {
            $color_temp = strtolower($color);
            if($color_temp{0} === '#' && $color_temp{1} == $color_temp{2} && $color_temp{3} == $color_temp{4} && $color_temp{5} == $color_temp{6})
            {
                $color = '#'.$color{1}.$color{3}.$color{5};
            }
        }

        switch(strtolower($color))
        {
            /* color name -> hex code */
            case 'black': return '#000';
            case 'fuchsia': return '#F0F';
            case 'white': return '#FFF';
            case 'yellow': return '#FF0';

            /* hex code -> color name */
            case '#800000': return 'maroon';
            case '#ffa500': return 'orange';
            case '#808000': return 'olive';
            case '#800080': return 'purple';
            case '#008000': return 'green';
            case '#000080': return 'navy';
            case '#008080': return 'teal';
            case '#c0c0c0': return 'silver';
            case '#808080': return 'gray';
            case '#f00': return 'red';
        }

        return $color;
    }

    /**
     * Compresses numbers (ie. 1.0 becomes 1 or 1.100 becomes 1.1 )
     * @param string $subvalue
     * @return string
     * @version 1.2
     */
    function compress_numbers($subvalue)
    {
        $unit_values =& $GLOBALS['csstidy']['unit_values'];
        $color_values =& $GLOBALS['csstidy']['color_values'];

        // for font:1em/1em sans-serif...;
        if($this->property === 'font')
        {
            $temp = explode('/',$subvalue);
        }
        else
        {
            $temp = array($subvalue);
        }
        for ($l = 0; $l < count($temp); $l++)
        {
            // if we are not dealing with a number at this point, do not optimise anything
			$number = $this->AnalyseCssNumber($temp[$l]);
			if ($number === false)
            {
                return $subvalue;
            }

            // Fix bad colors
            if (in_array($this->property, $color_values))
            {
                $temp[$l] = '#'.$temp[$l];
                continue;
            }
			
			if (abs($number[0]) > 0) {
				if ($number[1] == '' && in_array($this->property,$unit_values,true))
				{
					$number[1] = 'px';
				}
			} else {
				$number[1] = '';
			}
			
			$temp[$l] = $number[0] . $number[1];
        }

        return ((count($temp) > 1) ? $temp[0].'/'.$temp[1] : $temp[0]);
    }
	
	/**
	 * Checks if a given string is a CSS valid number. If it is,
	 * an array containing the value and unit is returned
	 * @param string $string
	 * @return array ('unit' if unit is found or '' if no unit exists, number value) or false if no number
	 */
	function AnalyseCssNumber($string)
	{
		// most simple checks first
		if (strlen($string) == 0 || ctype_alpha($string{0})) {
			return false;
		}
		
		$units =& $GLOBALS['csstidy']['units'];
		$return = array(0, '');
		
		$return[0] = floatval($string);
		if (abs($return[0]) > 0 && abs($return[0]) < 1) {
			if ($return[0] < 0) {
				$return[0] = '-' . ltrim(substr($return[0], 1), '0');
			} else {
				$return[0] = ltrim($return[0], '0');
			}
		}
		
		// Look for unit and split from value if exists
		foreach ($units as $unit)
		{
			$expectUnitAt = strlen($string) - strlen($unit);
			if( ! ($unitInString = stristr( $string, $unit )) )
			{ // mb_strpos() fails with "false"
				continue;
			}
			$actualPosition = strpos($string, $unitInString);
			if ($expectUnitAt === $actualPosition)
			{
				$return[1] = $unit;
				$string = substr($string, 0, - strlen($unit));
				break;
			}
		}
		if (!is_numeric($string)) {
			return false;
		}
		return $return;
	}

    /**
     * Merges selectors with same properties. Example: a{color:red} b{color:red} -> a,b{color:red}
     * Very basic and has at least one bug. Hopefully there is a replacement soon.
     * @param array $array
     * @return array
     * @access public
     * @version 1.2
     */
    function merge_selectors(&$array)
    {
        $css = $array;
        foreach($css as $key => $value)
        {
            if(!isset($css[$key]))
            {
                continue;
            }
            $newsel = '';

            // Check if properties also exist in another selector
            $keys = array();
            // PHP bug (?) without $css = $array; here
            foreach($css as $selector => $vali)
            {
                if($selector == $key)
                {
                    continue;
                }

                if($css[$key] === $vali)
                {
                    $keys[] = $selector;
                }
            }

            if(!empty($keys))
            {
                $newsel = $key;
                unset($css[$key]);
                foreach($keys as $selector)
                {
                    unset($css[$selector]);
                    $newsel .= ','.$selector;
                }
                $css[$newsel] = $value;
            }
        }
        $array = $css;
    }
    
    /**
     * Removes invalid selectors and their corresponding rule-sets as
     * defined by 4.1.7 in REC-CSS2. This is a very rudimentary check
     * and should be replaced by a full-blown parsing algorithm or
     * regular expression
     * @version 1.4
     */
    function discard_invalid_selectors(&$array) {
        $invalid = array('+' => true, '~' => true, ',' => true, '>' => true);
        foreach ($array as $selector => $decls) {
            $ok = true;
            $selectors = array_map('trim', explode(',', $selector));
            foreach ($selectors as $s) {
                $simple_selectors = preg_split('/\s*[+>~\s]\s*/', $s);
                foreach ($simple_selectors as $ss) {
                    if ($ss === '') $ok = false;
                    // could also check $ss for internal structure,
                    // but that probably would be too slow
                }
            }
            if (!$ok) unset($array[$selector]);
        }
    }

    /**
     * Dissolves properties like padding:10px 10px 10px to padding-top:10px;padding-bottom:10px;...
     * @param string $property
     * @param string $value
     * @return array
     * @version 1.0
     * @see merge_4value_shorthands()
     */
    function dissolve_4value_shorthands($property,$value)
    {
        $shorthands =& $GLOBALS['csstidy']['shorthands'];
        if(!is_array($shorthands[$property]))
        {
            $return[$property] = $value;
            return $return;
        }

        $important = '';
        if(csstidy::is_important($value))
        {
            $value = csstidy::gvw_important($value);
            $important = '!important';
        }
        $values = explode(' ',$value);


        $return = array();
        if(count($values) == 4)
        {
            for($i=0;$i<4;$i++)
            {
                $return[$shorthands[$property][$i]] = $values[$i].$important;
            }
        }
        elseif(count($values) == 3)
        {
            $return[$shorthands[$property][0]] = $values[0].$important;
            $return[$shorthands[$property][1]] = $values[1].$important;
            $return[$shorthands[$property][3]] = $values[1].$important;
            $return[$shorthands[$property][2]] = $values[2].$important;
        }
        elseif(count($values) == 2)
        {
            for($i=0;$i<4;$i++)
            {
                $return[$shorthands[$property][$i]] = (($i % 2 != 0)) ? $values[1].$important : $values[0].$important;
            }
        }
        else
        {
            for($i=0;$i<4;$i++)
            {
                $return[$shorthands[$property][$i]] = $values[0].$important;
            }
        }

        return $return;
    }

    /**
     * Explodes a string as explode() does, however, not if $sep is escaped or within a string.
     * @param string $sep seperator
     * @param string $string
     * @return array
     * @version 1.0
     */
    function explode_ws($sep,$string)
    {
        $status = 'st';
        $to = '';

        $output = array();
        $num = 0;
        for($i = 0, $len = strlen($string);$i < $len; $i++)
        {
            switch($status)
            {
                case 'st':
                if($string{$i} == $sep && !csstidy::escaped($string,$i))
                {
                    ++$num;
                }
                elseif($string{$i} === '"' || $string{$i} === '\'' || $string{$i} === '(' && !csstidy::escaped($string,$i))
                {
                    $status = 'str';
                    $to = ($string{$i} === '(') ? ')' : $string{$i};
                    (isset($output[$num])) ? $output[$num] .= $string{$i} : $output[$num] = $string{$i};
                }
                else
                {
                    (isset($output[$num])) ? $output[$num] .= $string{$i} : $output[$num] = $string{$i};
                }
                break;

                case 'str':
                if($string{$i} == $to && !csstidy::escaped($string,$i))
                {
                    $status = 'st';
                }
                (isset($output[$num])) ? $output[$num] .= $string{$i} : $output[$num] = $string{$i};
                break;
            }
        }

        if(isset($output[0]))
        {
            return $output;
        }
        else
        {
            return array($output);
        }
    }

    /**
     * Merges Shorthand properties again, the opposite of dissolve_4value_shorthands()
     * @param array $array
     * @return array
     * @version 1.2
     * @see dissolve_4value_shorthands()
     */
    function merge_4value_shorthands($array)
    {
        $return = $array;
        $shorthands =& $GLOBALS['csstidy']['shorthands'];

        foreach($shorthands as $key => $value)
        {
            if(isset($array[$value[0]]) && isset($array[$value[1]])
            && isset($array[$value[2]]) && isset($array[$value[3]]) && $value !== 0)
            {
                $return[$key] = '';

                $important = '';
                for($i = 0; $i < 4; $i++)
                {
                    $val = $array[$value[$i]];
                    if(csstidy::is_important($val))
                    {
                        $important = '!important';
                        $return[$key] .= csstidy::gvw_important($val).' ';
                    }
                    else
                    {
                        $return[$key] .= $val.' ';
                    }
                    unset($return[$value[$i]]);
                }
                $return[$key] = csstidy_optimise::shorthand(trim($return[$key].$important));
            }
        }
        return $return;
    }

    /**
     * Dissolve background property
     * @param string $str_value
     * @return array
     * @version 1.0
     * @see merge_bg()
     * @todo full CSS 3 compliance
     */
    function dissolve_short_bg($str_value)
    {
        $background_prop_default =& $GLOBALS['csstidy']['background_prop_default'];
        $repeat = array('repeat','repeat-x','repeat-y','no-repeat','space');
        $attachment = array('scroll','fixed','local');
        $clip = array('border','padding');
        $origin = array('border','padding','content');
        $pos = array('top','center','bottom','left','right');
        $important = '';
        $return = array('background-image' => null,'background-size' => null,'background-repeat' => null,'background-position' => null,'background-attachment'=>null,'background-clip' => null,'background-origin' => null,'background-color' => null);

        if(csstidy::is_important($str_value))
        {
            $important = ' !important';
            $str_value = csstidy::gvw_important($str_value);
        }

        $str_value = csstidy_optimise::explode_ws(',',$str_value);
        for($i = 0; $i < count($str_value); $i++)
        {
            $have['clip'] = false; $have['pos'] = false;
            $have['color'] = false; $have['bg'] = false;

            $str_value[$i] = csstidy_optimise::explode_ws(' ',trim($str_value[$i]));

            for($j = 0; $j < count($str_value[$i]); $j++)
            {
                if($have['bg'] === false && (substr($str_value[$i][$j],0,4) === 'url(' || $str_value[$i][$j] === 'none'))
                {
                    $return['background-image'] .= $str_value[$i][$j].',';
                    $have['bg'] = true;
                }
                elseif(in_array($str_value[$i][$j],$repeat,true))
                {
                    $return['background-repeat'] .= $str_value[$i][$j].',';
                }
                elseif(in_array($str_value[$i][$j],$attachment,true))
                {
                    $return['background-attachment'] .= $str_value[$i][$j].',';
                }
                elseif(in_array($str_value[$i][$j],$clip,true) && !$have['clip'])
                {
                    $return['background-clip'] .= $str_value[$i][$j].',';
                    $have['clip'] = true;
                }
                elseif(in_array($str_value[$i][$j],$origin,true))
                {
                    $return['background-origin'] .= $str_value[$i][$j].',';
                }
                elseif($str_value[$i][$j]{0} === '(')
                {
                    $return['background-size'] .= substr($str_value[$i][$j],1,-1).',';
                }
                elseif(in_array($str_value[$i][$j],$pos,true) || is_numeric($str_value[$i][$j]{0}) || $str_value[$i][$j]{0} === null || $str_value[$i][$j]{0} === '-')
                {
                    $return['background-position'] .= $str_value[$i][$j];
                    if(!$have['pos']) $return['background-position'] .= ' '; else $return['background-position'].= ',';
                    $have['pos'] = true;
                }
                elseif(!$have['color'])
                {
                    $return['background-color'] .= $str_value[$i][$j].',';
                    $have['color'] = true;
                }
            }
        }

        foreach($background_prop_default as $bg_prop => $default_value)
        {
            if($return[$bg_prop] !== null)
            {
                $return[$bg_prop] = substr($return[$bg_prop],0,-1).$important;
            }
            else $return[$bg_prop] = $default_value.$important;
        }
        return $return;
    }

    /**
     * Merges all background properties
     * @param array $input_css
     * @return array
     * @version 1.0
     * @see dissolve_short_bg()
     * @todo full CSS 3 compliance
     */
    function merge_bg($input_css)
    {
        $background_prop_default =& $GLOBALS['csstidy']['background_prop_default'];
        // Max number of background images. CSS3 not yet fully implemented
        $number_of_values = @max(count(csstidy_optimise::explode_ws(',',$input_css['background-image'])),count(csstidy_optimise::explode_ws(',',$input_css['background-color'])),1);
        // Array with background images to check if BG image exists
        $bg_img_array = @csstidy_optimise::explode_ws(',',csstidy::gvw_important($input_css['background-image']));
        $new_bg_value = '';
        $important = '';

        for($i = 0; $i < $number_of_values; $i++)
        {
            foreach($background_prop_default as $bg_property => $default_value)
            {
                // Skip if property does not exist
                if(!isset($input_css[$bg_property]))
                {
                    continue;
                }

                $cur_value = $input_css[$bg_property];

                // Skip some properties if there is no background image
                if((!isset($bg_img_array[$i]) || $bg_img_array[$i] === 'none')
                    && ($bg_property === 'background-size' || $bg_property === 'background-position'
                    || $bg_property === 'background-attachment' || $bg_property === 'background-repeat'))
                {
                    continue;
                }

                // Remove !important
                if(csstidy::is_important($cur_value))
                {
                    $important = ' !important';
                    $cur_value = csstidy::gvw_important($cur_value);
                }

                // Do not add default values
                if($cur_value === $default_value)
                {
                    continue;
                }

                $temp = csstidy_optimise::explode_ws(',',$cur_value);

                if(isset($temp[$i]))
                {
                    if($bg_property === 'background-size')
                    {
                        $new_bg_value .= '('.$temp[$i].') ';
                    }
                    else
                    {
                        $new_bg_value .= $temp[$i].' ';
                    }
                }
            }

            $new_bg_value = trim($new_bg_value);
            if($i != $number_of_values-1) $new_bg_value .= ',';
        }

        // Delete all background-properties
        foreach($background_prop_default as $bg_property => $default_value)
        {
            unset($input_css[$bg_property]);
        }

        // Add new background property
        if($new_bg_value !== '') $input_css['background'] = $new_bg_value.$important;

        return $input_css;
    }
}
