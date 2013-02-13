<?php

 /*
   H. Elwood Gilliland III_proudly presents:
    _ __ ___   ___   __| |___ \ __ _ _ __  ___
   | '_ ` _ \ / _ \ / _` | __) / _` | '_ \/ __|
   | | | | | | (_) | (_| |/ __/ (_| | | | \__ \
   |_| |_| |_|\___/ \__,_|_____\__,_|_| |_|___/
 converts amiga mod files to ansi files using php
Copyright (c) 2013 - Released under New BSD License
 */

 $source = "YOUR.MOD";
 $target = "your.ans";
 $speed='L8';
 $rest='P8';


 $fp = fopen($source, "rb");

 $i=0;
 $data = array();
 while ( !feof($fp) ) {
  $data[$i] = fread($fp,1);
  if ( feof($fp) ) break;
  $data[$i] = ord($data[$i][0]);
  $i++;
 }

 echo count($data) . ' bytes' . PHP_EOL;

 fclose($fp);

 $title="";

 $i=0;
 for ( ; $i<20; $i++ ) {
  if ( $data[$i] != 0 ) $title.=chr(intval($data[$i]));
 }

 echo 'Title: "'. $title . '"' . PHP_EOL;

 // Skip the null at the end

 // Combines two bytes into a word
 function bytes2word( $aint, $bint ) {
  $hi = ($aint);
  $lo = ($bint);
  return ( $hi << 8 ) | $lo;
 }

 // Disassembles a byte into two nibbles.
 function nybbles( $b ) {
  return array( 'lo'=> ($b & 0x0F), 'hi'=> (($b & 0xF0) >> 4) );
 }

 // Assembles a byte from two nibbles.
 function bytes( $hi, $lo ) {
  return ($hi << 4) | $lo;
 }

 // Decodes a channel data.
 function decode4chan( $b1, $b2, $b3, $b4 ) {
  $n1=nybbles($b1);
  $n2=nybbles($b3);
  return array(
   'sample'=> bytes( $n1['hi'], $n2['hi'] ),
   'period'=> bytes2word( bytes( 0, $n1['lo'] ), $b2 ),
   'effect'=> bytes2word( bytes( 0, $n2['lo'] ), $b4 )
  );
 }

 function sample( $n, $data, &$i ) {
  $sample=array(
   'title'=>'',
   'length'=>0,
   'finetune'=>0,
   'volume'=>0,
   'startoffset'=>0,
   'lengthrepeat'=>0,
   'raw'=>0
  );
  $j=0;
  for ( $j=0; $j<22; $j++ ) {
   if ( intval($data[$i]) !=0 )
    $sample['title'].=chr(intval($data[$i]));
   $i++;
  }
  $sample['length']=bytes2word($data[$i],$data[$i]+1)*2;  // Multiply by two to get real sample length in bytes.  Ignore '1'
  $i+=2;
  $sample['finetune']=$data[$i];
  $i++;
  $sample['volume']=$data[$i];
  $i++;
  $sample['startoffset']=bytes2word($data[$i],$data[$i]+1);
  $i+=2;
  $sample['lengthrepeat']=bytes2word($data[$i],$data[$i]+1);
  $i+=2;
  // read sample data later
  $sample['raw'] = array();
  echo 'Read sample '.$n.' from header: "'.$sample['title'].'" of length '. $sample['length'] .PHP_EOL;
  return $sample;
 }

 $samples=array();
 $total_sample_data_size=0;
 for ( $k=0; $k<31; $k++ ) {
  $samples[$k]=sample($k,$data,$i);
  $total_sample_data_size+=($samples[$k]['length']);
 }

 $song=array();
 $song['positions']=($data[$i]);
 $i++;

 echo 'Song claims '.$song['positions'].' patterns'.PHP_EOL;

 $song['ignored']=$data[$i];
 // noisetracker uses this byte for restart pattern
 $i++;

 $song['pattern_table']=array();

 echo 'Reading the pattern table...'.PHP_EOL;
 for ( $k=0; $k<128; $k++ ) {
  $song['pattern_table'][$k]=($data[$i]);
  $i++;
 }

 //var_dump($song['pattern_table']);

 $song['M.K.']=
  chr(($data[$i]))
 .chr(($data[$i+1]))
 .chr(($data[$i+2]))
 .chr(($data[$i+3]))
 ;
 $i+=4;

 function is( $a, $b ) { // function may need rethought
  if ( $a[0] === $b[0]
    && $a[1] === $b[1]
    && $a[2] === $b[2]
    && $a[3] === $b[3] ) return true;
  return false;
 }

 echo 'Byte '. ($i-4) .': Decoding 4-byte Mahoney & Kaktus code: "'. $song['M.K.'] . '":'.PHP_EOL;

 $channels=4;
 $patterns=64;
 $mo_samples=false;
 if ( $song['M.K.'][0]==chr(0)
  &&  $song['M.K.'][1]==chr(0)
  &&  $song['M.K.'][2]==chr(0)
  &&  $song['M.K.'][3]==chr(0) ) // empty...
 {
  echo '15 samples only?? seems like there are always 30.. hmm' . PHP_EOL;
  $mo_samples=true;
 } else if ( is($song['M.K.'],'M.K.') ) {
  echo '31 sample file M.K. Unknown/D.O.C.';
  $mo_samples=true;
 } else if ( is($song['M.K.'],'FLT4') === true ) {
  echo '4 channel Startrekker file';
  $mo_samples=true;
 } else if ( is($song['M.K.'],'FLT8') === true ) {
  echo '8 channel Startrekker file';
  $mo_samples=true;
  $channels=8;
 } else if ( is($song['M.K.'],'M!K!') === true ) {
  echo '>64 patterns indicated (Protracker MOD format)';
  $mo_samples=true;
  $patterns=128;
 } else if ( is($song['M.K.'],'6CHN') === true ) {
  echo '6 channel MOD (Protracker)';
  $mo_samples=true;
  $channels=6;
 } else if ( is($song['M.K.'],'8CHN') === true ) {
  echo '8 channel MOD (Protracker)';
  $mo_samples=true;
  $channels=8;
 } else {
  echo '30 samples!';
  $mo_samples=true;
 }

 echo '# channels ' . $channels . PHP_EOL;

/// This is stupid, but you have to assume more samples without knowing.
/// To fix this, test byte 1080 and byte

// if ( $mo_samples ) {
//  for ( $k=0; $k<16; $k++ ) {
//   $samples[$k+15]=sample($k+15,$data,$i);
//   $total_sample_data_size+=intval($samples[$k+15]['length']);
//  }
// }

 function read_patterns( $data, $num_pat, $num_chan, &$i ) {
  $pattern=array();
  for ( $a=0; $a<$num_pat; $a++ ) {
   $read=0;
   echo 'Reading pattern '.$a.' of '.$num_pat;
   $pattern[$a]=array();
   for ( $division=0; $division<64; $division++ ) {
    for ( $b=0; $b<$num_chan; $b++ ) {
     $b1=$data[$i];
     $b2=$data[$i+1];
     $b3=$data[$i+2];
     $b4=$data[$i+3];
     $i+=4;
     $pattern[$a][$division][$b]=decode4chan($b1,$b2,$b3,$b4);
     $read+=4;
    }
   }
   echo ': '.$read.' bytes'.PHP_EOL;
  }
  return $pattern;
 }

 echo 'At byte '.$i.' reading patterns' . PHP_EOL;

 $song['patterns'] = read_patterns( $data, intval($song['positions']), $channels, $i ); // didn't use $patterns

 function read_sample_data( $data, $len, &$i ) {
  $total=intval($len);
  echo 'reading sample of length '.$total.' starting at byte '.$i.PHP_EOL;
  $raw=array();
  for ( $b=0; $b<$total; $b++ ) {
   $raw[$b]=127-intval($data[$i]);
   $i++;
  }
  return $raw;
 }

 $total_samples=count($samples);

 //var_dump($samples);

 for ( $s=0; $s<$total_samples; $s++ ) {
  echo $s.': ';
  $samples[$s]['raw']=read_sample_data($data,$samples[$s]['length'],$i);
 }

 echo 'i was at byte '.$i.' of '
      .count($data).' when i finished examining the file'.PHP_EOL;

 echo '---------------------------------------------'.PHP_EOL;

 echo 'decoding the patterns and playlist into ansi'.PHP_EOL;

// var_dump($song['patterns']);


/*
Periodtable for Tuning 0, Normal
  C-1 to B-1 : 856,808,762,720,678,640,604,570,538,508,480,453
  C-2 to B-2 : 428,404,381,360,339,320,302,285,269,254,240,226
  C-3 to B-3 : 214,202,190,180,170,160,151,143,135,127,120,113

          C    C#   D    D#   E    F    F#   G    G#   A    A#   B
Octave 1: 856, 808, 762, 720, 678, 640, 604, 570, 538, 508, 480, 453
Octave 2: 428, 404, 381, 360, 339, 320, 302, 285, 269, 254, 240, 226
Octave 3: 214, 202, 190, 180, 170, 160, 151, 143, 135, 127, 120, 113

Octave 0:1712,1616,1525,1440,1357,1281,1209,1141,1077,1017, 961, 907
Octave 4: 107, 101,  95,  90,  85,  80,  76,  71,  67,  64,  60,  57

 */

 global $note; $note =  array(
  1712=>'O5C',
  1616=>'O5C+',
  1525=>'O5D',
  1440=>'O5D+',
  1357=>'O5E',
  1281=>'O5F',
  1209=>'O5F+',
  1141=>'O5G',
  1077=>'O5G+',
  1017=>'O5A',
   961=>'O5A+',
   907=>'O5B',

   856=>'O4C',
   808=>'O4C+',
   762=>'O4D',
   720=>'O4D+',
   678=>'O4E',
   640=>'O4F',
   604=>'O4F+',
   570=>'O4G',
   538=>'O4G+',
   508=>'O4A',
   480=>'O4A+',
   453=>'O4B',

   428=>'O3C',
   404=>'O3C+',
   381=>'O3D',
   360=>'O3D+',
   339=>'O3E',
   320=>'O3F',
   302=>'O3F+',
   285=>'O3G',
   269=>'O3G+',
   254=>'O3A',
   240=>'O3A+',
   226=>'O3B',

   214=>'O2C',
   202=>'O2C+',
   190=>'O2D',
   180=>'O2D+',
   170=>'O2E',
   160=>'O2F',
   151=>'O2F+',
   143=>'O2G',
   135=>'O2G+',
   127=>'O2A',
   120=>'O2A+',
   113=>'O2B',

   107=>'O1C',
   101=>'O1C+',
    95=>'O1D',
    90=>'O1D+',
    85=>'O1E',
    80=>'O1F',
    76=>'O1F+',
    71=>'O1G',
    67=>'O1G+',
    64=>'O1A',
    60=>'O1A+',
    57=>'O1A'
 );

 function lookup( $period ) {
  global $note;
  $candidate='';
  foreach ( $note as $p=>$v ) {
   if ( $period == $p ) return $v;
//   $candidate=$v;
//   if ( $period < $p ) return $candidate;
  }
 }

 // 0=fore 1=back 2=fx

 $ansi=array();
 $ansi[0]=array();
 $ansi[1]=array();
 $ansi[2]=array();

 foreach ( $song['patterns'] as $pattern ) {
  foreach ( $pattern as $division ) {
   $used=0;
   foreach ( $division as $channel ) {
    if ( $channel['period'] != 0 && $used < 3 ) {
     $ansi[$used][]=lookup($channel['period']);
     $used++;
    }
   }
   if ( $used < 3 ) { // fill channels that are unused with rests?
    while ( $used < 3 ) $ansi[$used++][]=$rest;
   }
  }
 }

 file_put_contents($target,
 'MF'.$speed.implode('',$ansi[0]).PHP_EOL
.'MB'.$speed.implode('',$ansi[1]).PHP_EOL
.'MX'.$speed.implode('',$ansi[2])
 );
