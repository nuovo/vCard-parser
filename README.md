Nuovo/Nouveau vCard-parser is a simple vCard file parser with the focus on ease of use.

The parser was written mostly because I couldn't find one that I was satisfied with - all those that I tried either failed with real world data or were too unwieldy or inconvenient, hence this parser.

The parser can read both single and multiple vCards from a single file and with the help of PHP's magic methods and interfaces it can be written concisely. For example:

    include('vCard.php');
    $vCard = new vCard('Example3.0.vcf');

Get the number of vCards in the file

    echo count($vCard);

In the single-vCard mode every element is accessible directly.

    if (count($vCard) == 1)
    {
        print_r($vCard -> n);
        print_r($vCard -> tel);
    }

In the multiple-vCard mode the object can be used as an array to retrieve separate vCard objects for each vCard in the file.

    else
    {
        foreach ($vCard as $vCardPart)
        {
            print_r($vCardPart -> n);
            print_r($vCardPart -> tel);
        }
    }

Every vCard element is accessible as an object member by the vCard element name. Every element is an array with the data parsed out of the file.
It is possible to specify an option to the vCard constructor that will let you access every element as a single value in cases where there is just one value, e.g.:

    $vCard = new vCard('Example3.0.vcf', false, array('Collapse' => true));

More on usage in [the wiki](https://github.com/nuovo/vCard-parser/wiki)

See also:
* http://tools.ietf.org/html/rfc2425 - A MIME Content-Type for Directory Information
* http://tools.ietf.org/html/rfc2426 - vCard MIME directory profile
* http://tools.ietf.org/html/rfc4770 - vCard Extensions for Instant Messaging (IM)

TODOs planned:
* Add support for non-standard ("X-...") elements;

http://www.nuovo.lv