<?php
/* Osmium
 * Copyright (C) 2013, 2014 Romain "Artefact2" Dalmaso <artefact2@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

namespace Osmium\Page\About;

require __DIR__.'/../inc/root.php';

\Osmium\Chrome\print_header('About Osmium');

echo "<div id='mdstatic'>\n";

echo "<h1>About Osmium</h1>\n";

echo \Osmium\Chrome\format_md(
	file_get_contents(__DIR__.'/md/about.md')
);

echo "<h2 id='contact'>Contact</h2>\n";

echo \Osmium\Chrome\format_md(
	\Osmium\get_ini_setting('contact')
);

echo "<h2>Get the source code</h2>\n";

echo \Osmium\Chrome\format_md(
	"The full source code of this instance of Osmium should be available at <".\Osmium\get_ini_setting('source').">. If you believe this is not the case, please contact the administrators about a possible AGPL violation."
);

echo \Osmium\Chrome\format_md(
	file_get_contents(__DIR__.'/md/about-disclaimers.md')
);

echo "<h2>Javascript license information</h2>\n";
echo "<table class='d' id='jslicense-labels1'>\n";
echo "<thead>\n<tr>\n";
echo "<th>Script name</th>\n<th>License</th>\n<th>Non-obfuscated source</th>\n";
echo "</tr>\n</thead>\n<tbody>\n";

echo "<tr><td><a href='//cdnjs.cloudflare.com/ajax/libs/jquery/1.10.2/jquery.min.js'>jquery.min.js</a></td><td><a href='https://raw.github.com/jquery/jquery/master/MIT-LICENSE.txt'>MIT</a></td><td><a href='https://cdnjs.cloudflare.com/ajax/libs/jquery/1.10.2/jquery.js'>https://cdnjs.cloudflare.com/ajax/libs/jquery/1.10.2/jquery.js</a></td></tr>\n";

echo "<tr><td><a href='//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.min.js'>jquery-ui.min.js</a></td><td><a href='https://raw.github.com/jquery/jquery-ui/master/MIT-LICENSE.txt'>MIT</a></td><td><a href='https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.js'>https://cdnjs.cloudflare.com/ajax/libs/jqueryui/1.10.3/jquery-ui.js</a></td></tr>\n";

echo "<tr><td><a href='https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/0.4.6/jquery.perfect-scrollbar-with-mousewheel.min.js'>perfect-scrollbar-with-mousewheel.min.js</a></td><td><a href='http://www.yuiazu.net/perfect-scrollbar/'>MIT</a></td><td><a href='https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/0.4.6/jquery.perfect-scrollbar-with-mousewheel.js'>https://cdnjs.cloudflare.com/ajax/libs/jquery.perfect-scrollbar/0.4.6/jquery.perfect-scrollbar-with-mousewheel.js</a></td></tr>\n";

echo "<tr><td><a href='./static/jquery.jsPlumb-1.5.4-min.js'>jquery.jsPlumb-1.5.4-min.js</a></td><td><a href='https://github.com/sporritt/jsPlumb/blob/1.5.4/jsPlumb-MIT-LICENSE.txt'>MIT</a></td><td><a href='https://raw.github.com/sporritt/jsPlumb/1.5.4/dist/js/jquery.jsPlumb-1.5.4.js'>https://raw.github.com/sporritt/jsPlumb/1.5.4/dist/js/jquery.jsPlumb-1.5.4.js</a></td></tr>\n";

echo "<tr><td><a href='./static-1/rawdeflate.min.js'>rawdeflate.min.js</a></td><td><a href='http://opensource.org/licenses/GPL-2.0'>GNU-GPL-2.0-only</a><br /><a href='http://opensource.org/licenses/mit-license'>MIT</a></td><td><a href='https://raw.github.com/dankogai/js-deflate/1cc649243c7e0ada065b880180bdccce3c2dbcc2/rawdeflate.js'>https://raw.github.com/dankogai/js-deflate/1cc649243c7e0ada065b880180bdccce3c2dbcc2/rawdeflate.js</a><br /><a href='https://raw.github.com/dankogai/js-deflate/1cc649243c7e0ada065b880180bdccce3c2dbcc2/test/base64.js'>https://raw.github.com/dankogai/js-deflate/1cc649243c7e0ada065b880180bdccce3c2dbcc2/test/base64.js</a></td></tr>\n";

chdir(__DIR__.'/../static/cache');
foreach(glob('JS_*.min.js') as $min) {
	$full = substr($min, 0, -strlen('.min.js')).".js";
	echo "<tr><td><a href='./static-".\Osmium\JS_STATICVER."/cache/{$min}'>{$min}</a></td><td><a href='./static/copying.txt'>GNU-AGPL-3.0-or-later</a></td><td><a href='./static-".\Osmium\JS_STATICVER."/cache/{$full}'>{$full}</a></td></tr>\n";
}

echo "</tbody>\n</table>\n";

echo "</div>\n";
\Osmium\Chrome\print_footer();
