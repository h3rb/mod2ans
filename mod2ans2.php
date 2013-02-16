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

 // This version calculates a note length and approximates odd-length notes.
 // It forces "Legato" mode

 $source = "TREK.MOD";
 $target = "krak.ans";
 $speed='MLT32';
 $skip_first=false;
 $timesig=16;
// $skip_first=true;


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
  $n3=nybbles($b4);
  return array(
   'sample'=> bytes( $n1['hi'], $n2['hi'] ),
   'period'=> bytes2word( bytes( 0, $n1['lo'] ), $b2 ),
   'effect'=> array( 'code'=>$n2['lo'], 'x'=>$n3['hi'], 'y'=>$n3['lo'] )
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
  1712=>'O4C',
  1616=>'O4C+',
  1525=>'O4D',
  1440=>'O4D+',
  1357=>'O4E',
  1281=>'O4F',
  1209=>'O4F+',
  1141=>'O4G',
  1077=>'O4G+',
  1017=>'O4A',
   961=>'O4A+',
   907=>'O4B',

   856=>'O3C',
   808=>'O3C+',
   762=>'O3D',
   720=>'O3D+',
   678=>'O3E',
   640=>'O3F',
   604=>'O3F+',
   570=>'O3G',
   538=>'O3G+',
   508=>'O3A',
   480=>'O3A+',
   453=>'O3B',

   428=>'O2C',
   404=>'O2C+',
   381=>'O2D',
   360=>'O2D+',
   339=>'O2E',
   320=>'O2F',
   302=>'O2F+',
   285=>'O2G',
   269=>'O2G+',
   254=>'O2A',
   240=>'O2A+',
   226=>'O2B',

   214=>'O1C',
   202=>'O1C+',
   190=>'O1D',
   180=>'O1D+',
   170=>'O1E',
   160=>'O1F',
   151=>'O1F+',
   143=>'O1G',
   135=>'O1G+',
   127=>'O1A',
   120=>'O1A+',
   113=>'O1B',

   107=>'O0C',
   101=>'O0C+',
    95=>'O0D',
    90=>'O0D+',
    85=>'O0E',
    80=>'O0F',
    76=>'O0F+',
    71=>'O0G',
    67=>'O0G+',
    64=>'O0A',
    60=>'O0A+',
    57=>'O0B'
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

 var_dump($song['patterns']);


 // build a note length profile
 $notes=array( 0=>array(), 1=>array(), 2=>array(), 3=>array() );
 $lasts=array(
  0=>array('note'=>'','length'=>0),
  1=>array('note'=>'','length'=>0),
  2=>array('note'=>'','length'=>0),
  3=>array('note'=>'','length'=>0)
 );
 $notecount=array( 0=>0, 1=>0, 2=>0, 3=>0 );
 foreach ( $song['patterns'] as $pattern ) {
  foreach ( $pattern as $division ) {
   $used=0;
   foreach ( $division as $channel ) {
    // This means a "rest"
    if ( $channel['effect']['code'] == 12
      && $channel['effect']['x'] == 0
      && $channel['effect']['y'] == 0 ) {
     if ( $lasts[$used]['length'] != 0 ) $notes[$used][$notecount[$used]++]=$lasts[$used];
     $lasts[$used]['length']=1; // should this be 1?
     $lasts[$used]['note']='';
    } else
    if ( $channel['period'] != 0 ) {
     // this means the note changed
     if ( $lasts[$used]['length'] != 0 ) $notes[$used][$notecount[$used]++]=$lasts[$used];
     $lasts[$used]['note']=lookup($channel['period']);
     $lasts[$used]['length']=1;
    } else {
     // this mean the note was sustained
     $lasts[$used]['length']++;
    }
    $used++;
   }
  }
 }
// if ( $lasts[0]['length'] != 0 && $last[0]['length'] <= $timesig ) $notes[0][$notecount[0]++]=$lasts[$used];
// if ( $lasts[1]['length'] != 0 && $last[1]['length'] <= $timesig ) $notes[1][$notecount[1]++]=$lasts[$used];
// if ( $lasts[2]['length'] != 0 && $last[2]['length'] <= $timesig ) $notes[2][$notecount[2]++]=$lasts[$used];
// if ( $lasts[3]['length'] != 0 && $last[3]['length'] <= $timesig ) $notes[3][$notecount[3]++]=$lasts[$used];

// array_shift($notes[0]); // pop the top off because its usually a space before the music starts.
// array_shift($notes[1]); // pop the top off because its usually a space before the music starts.
// array_shift($notes[2]); // pop the top off because its usually a space before the music starts.
// array_shift($notes[3]); // pop the top off because its usually a space before the music starts.

// var_dump($notes);
 $beatcounts=array( 0=>0, 1=>0, 2=>0, 3=>0 );
 $maxmins=array(
  0=>array( 'max'=>0, 'min'=>100000 ),
  1=>array( 'max'=>0, 'min'=>100000 ),
  2=>array( 'max'=>0, 'min'=>100000 ),
  3=>array( 'max'=>0, 'min'=>100000 )
 );
 for ( $ch=0; $ch<4; $ch++ ) {
  foreach ( $notes[$ch] as $data ) if ( strlen($data['note']) > 0 ) {
   $beatcounts[$ch]+=$data['length'];
   if ( $data['length'] < $maxmins[$ch]['min'] && $data['length'] > 0 ) $maxmins[$ch]['min']=$data['length'];
   if ( $data['length'] > $maxmins[$ch]['max'] ) $maxmins[$ch]['max']=$data['length'];
  }
 }

 // used to create an appropriate timescale
 $beats=array( 'max'=>0, 'min'=>100000 );
 foreach ( $maxmins as $mm ) {
  if ( $mm['max'] > $beats['max'] ) $beats['max'] = $mm['max'];
  if ( $mm['min'] < $beats['min'] ) $beats['min'] = $mm['min'];
 }

 $longest=0;
 foreach ( $beatcounts as $bc ) {
  if ( $bc > $longest ) $longest = $bc;
 }

 var_dump($beatcounts);
 var_dump($maxmins);
 var_dump($beats);
// var_dump($notes);
 echo 'longest = '.$longest.PHP_EOL;

 $lengths=array();
 for ( $ch=0; $ch<3; $ch++ ) {
  foreach ( $notes[$ch] as $data ) {
   $lengths[]=$data['length'];
  }
 }
 sort($lengths,SORT_NUMERIC);

 echo 'Lengths ='.PHP_EOL;
 var_dump($lengths);
 echo 'Unique lengths = '.PHP_EOL;
 $lengths=array_unique($lengths); //,SORT_NUMERIC);
 var_dump($lengths);


 // Method 1: Just put chan 0 into ansi chan 0, chan 1 into ansi chan 1, etc

 $timescalearray = array(
  1 =>array('L64'),
  2 =>array('L32'),
  3 =>array('L32','L64'),
  4 =>array('L16'),
  5 =>array('L16','L64'),
  6 =>array('L16','L32'),
  7 =>array('L16','L32','L64'),
  8 =>array('L8'),
  9 =>array('L8','L64'),
  10=>array('L8','L32'),
  11=>array('L8','L32','L64'),
  12=>array('L8','L16'),
  13=>array('L8','L16','L64'),
  14=>array('L8','L16','L32'),
  15=>array('L8','L16','L32','L64'),
  16=>array('L4'),
  17=>array('L4','L64'),
  18=>array('L4','L32'),
  19=>array('L4','L32','L64'),
  20=>array('L4','L16'),
  21=>array('L4','L16','L64'),
  22=>array('L4','L16','L32'),
  23=>array('L4','L16','L32','L64'),
  24=>array('L4','L8'),
  25=>array('L4','L8','L64'),
  26=>array('L4','L8','L32'),
  27=>array('L4','L8','L32','L64'),
  28=>array('L4','L8','L16'),
  29=>array('L4','L8','L16','L64'),
  30=>array('L4','L8','L16','L32'),
  31=>array('L4','L8','L16','L32','L64'),
  32=>array('L2'),
  33=>array('L2','L64'),
  34=>array('L2','L32'),
  35=>array('L2','L32','L64'),
  36=>array('L2','L16'),
  37=>array('L2','L16','L64'),
  38=>array('L2','L16','L32'),
  39=>array('L2','L16','L32','L64'),
  40=>array('L2','L8'),
  41=>array('L2','L8','L64'),
  42=>array('L2','L8','L32'),
  43=>array('L2','L8','L32','L64'),
  44=>array('L2','L8','L16'),
  45=>array('L2','L8','L16','L64'),
  46=>array('L2','L8','L16','L32'),
  47=>array('L2','L8','L16','L32','L64'),
  48=>array('L2','L4'),
  49=>array('L2','L4','L64'),
  50=>array('L2','L4','L32'),
  51=>array('L2','L4','L32','L64'),
  52=>array('L2','L4','L16'),
  53=>array('L2','L4','L16','L64'),
  54=>array('L2','L4','L16','L32'),
  55=>array('L2','L4','L16','L32','L64'),
  57=>array('L2','L4','L8'),
  58=>array('L2','L4','L8','L64'),
  59=>array('L2','L4','L8','L32'),
  60=>array('L2','L4','L8','L32','L64'),
  61=>array('L2','L4','L8','L16'),
  62=>array('L2','L4','L8','L16','L64'),
  63=>array('L2','L4','L8','L16','L32'),
  64=>array('L2','L4','L8','L16','L32','L64'),
  65=>array('L1')
 );

 $restscalearray = array(
  1 =>array('P64'),
  2 =>array('P32'),
  3 =>array('P32','P64'),
  4 =>array('P16'),
  5 =>array('P16','P64'),
  6 =>array('P16','P32'),
  7 =>array('P16','P32','P64'),
  8 =>array('P8'),
  9 =>array('P8','P64'),
  10=>array('P8','P32'),
  11=>array('P8','P32','P64'),
  12=>array('P8','P16'),
  13=>array('P8','P16','P64'),
  14=>array('P8','P16','P32'),
  15=>array('P8','P16','P32','P64'),
  16=>array('P4'),
  17=>array('P4','P64'),
  18=>array('P4','P32'),
  19=>array('P4','P32','P64'),
  20=>array('P4','P16'),
  21=>array('P4','P16','P64'),
  22=>array('P4','P16','P32'),
  23=>array('P4','P16','P32','P64'),
  24=>array('P4','P8'),
  25=>array('P4','P8','P64'),
  26=>array('P4','P8','P32'),
  27=>array('P4','P8','P32','P64'),
  28=>array('P4','P8','P16'),
  29=>array('P4','P8','P16','P64'),
  30=>array('P4','P8','P16','P32'),
  31=>array('P4','P8','P16','P32','P64'),
  32=>array('P2'),
  33=>array('P2','P64'),
  34=>array('P2','P32'),
  35=>array('P2','P32','P64'),
  36=>array('P2','P16'),
  37=>array('P2','P16','P64'),
  38=>array('P2','P16','P32'),
  39=>array('P2','P16','P32','P64'),
  40=>array('P2','P8'),
  41=>array('P2','P8','P64'),
  42=>array('P2','P8','P32'),
  43=>array('P2','P8','P32','P64'),
  44=>array('P2','P8','P16'),
  45=>array('P2','P8','P16','P64'),
  46=>array('P2','P8','P16','P32'),
  47=>array('P2','P8','P16','P32','P64'),
  48=>array('P2','P4'),
  49=>array('P2','P4','P64'),
  50=>array('P2','P4','P32'),
  51=>array('P2','P4','P32','P64'),
  52=>array('P2','P4','P16'),
  53=>array('P2','P4','P16','P64'),
  54=>array('P2','P4','P16','P32'),
  55=>array('P2','P4','P16','P32','P64'),
  57=>array('P2','P4','P8'),
  58=>array('P2','P4','P8','P64'),
  59=>array('P2','P4','P8','P32'),
  60=>array('P2','P4','P8','P32','P64'),
  61=>array('P2','P4','P8','P16'),
  62=>array('P2','P4','P8','P16','P64'),
  63=>array('P2','P4','P8','P16','P32'),
  64=>array('P2','P4','P8','P16','P32','P64'),
  65=>array('P1')
 );
 $ansi=array();
 $ansi[0]=array();
 $ansi[1]=array();
 $ansi[2]=array();
 $float_sig=floatval($timesig); // not reliable: floatval($beats['max']);
 for ( $ch=0; $ch<3; $ch++ ) {
  $note_number=0;
  foreach ( $notes[$ch] as $data ) {
   if ( $data['length'] === NULL ) $data['length']=0;
   if ( $data['length']==0 ) continue;
   if ( $skip_first !==false && $note_number==0 ) { // skip first note which is usually a rest on each channel due to conversion from midi
    $note_number++;
    continue;
   }
   if ( strlen($data['note']) == 0 ) // rest
   {
    foreach ( $restscalearray[$data['length']] as $rest ) {
     $ansi[$ch][]=$rest;
    }
   }
   else // note 
   {
    foreach ( $timescalearray[$data['length']] as $noteseg ) {
     $ansi[$ch][]=$noteseg.$data['note'];
    }
   }
/* another method:
   $notesize =$timesig / $data['length']; // 32 = 2, 16 = 1, 1 =
   $notespace=$timesig % $data['length']; // 33 = 1, 34 =
   if ( strlen($data['note']) == 0 ) // rest
   $ansi[$ch][]='P'.$notesize;
   else // note
   $ansi[$ch][]='L'.$notesize.$data['note'];
   for ( $i=0; $i<$notespace; $i++ )
   $ansi[$ch][]='P'.$timesig;
 */
   $note_number++;
  }
 }

 echo 'MF'.$speed.implode('',$ansi[0]).PHP_EOL
     .'MB'.$speed.implode('',$ansi[1]).PHP_EOL
     .'MX'.$speed.implode('',$ansi[2]);

 // Method 2: TODO: fill "best" spaces with extra notes in channel 4 to simulate a fourth channel



 die;

 // 0=fore 1=back 2=fx

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

 echo 'MF'.$speed.implode('',$ansi[0]).PHP_EOL
     .'MB'.$speed.implode('',$ansi[1]).PHP_EOL
     .'MX'.$speed.implode('',$ansi[2]);
