// If you want to suggest a new language you can use this file as a template.
// To reduce the file size you should remove the comment lines (the ones that start with // )
if(!window.calendar_languages) {
	window.calendar_languages = {};
}
// Here you define the language and Country code. Replace en-US with your own.
// First letters: the language code (lower case). See http://www.loc.gov/standards/iso639-2/php/code_list.php
// Last letters: the Country code (upper case). See http://www.iso.org/iso/home/standards/country_codes/country_names_and_code_elements.htm
window.calendar_languages['hu-HU'] = {
	error_noview: 'Naptár: A {0}. viewt nem találom',
	error_dateformat: 'Naptár: Rossz dátumformátum {0}. Vagy "now" vagy "yyyy-mm-dd" legyen',
	error_loadurl: 'Naptár: Nincs meghatározva az esemény URL-je',
	error_where: 'Naptár: Rossz lapozási irány {0}. Csak "next", "prev" vagy "today" lehet',
	error_timedevide: 'Naptár: Az időszak paraméternek 60 osztójának kell lennie. Például 10, 15, 30',

	no_events_in_day: 'Ezen a napon nincs esemény.',

	// {0} will be replaced with the year (example: 2013)
	title_year: '{0}',
	// {0} will be replaced with the month name (example: September)
	// {1} will be replaced with the year (example: 2013)
	title_month: '{1} {0}',
	// {0} will be replaced with the week number (example: 37)
	// {1} will be replaced with the year (example: 2013)
	title_week: '{1} {0}. hét',
	// {0} will be replaced with the weekday name (example: Thursday)
	// {1} will be replaced with the day of the month (example: 12)
	// {2} will be replaced with the month name (example: September)
	// {3} will be replaced with the year (example: 2013)
	title_day: '{3}. {2} {1}., {0}',

	week:'{0}. hét',
	all_day:     'Egész nap',
	time:        'Idő',
	events:      'Események',
	before_time: 'Az időszakot megelőzően',
	after_time:  'Az időszakot követően',

	m0: 'Január',
	m1: 'Február',
	m2: 'Március',
	m3: 'Április',
	m4: 'Május',
	m5: 'Június',
	m6: 'Július',
	m7: 'Augusztus',
	m8: 'Szeptember',
	m9: 'Október',
	m10: 'November',
	m11: 'December',

	ms0: 'Jan',
	ms1: 'Feb',
	ms2: 'Már',
	ms3: 'Ápr',
	ms4: 'Máj',
	ms5: 'Jún',
	ms6: 'Júl',
	ms7: 'Aug',
	ms8: 'Sze',
	ms9: 'Okt',
	ms10: 'Nov',
	ms11: 'Dec',

	d0: 'Vasárnap',
	d1: 'Hétfő',
	d2: 'Kedd',
	d3: 'Szerda',
	d4: 'Csütörtök',
	d5: 'Péntek',
	d6: 'Szombat',

	// Which is the first day of the week (2 for sunday, 1 for monday)
	first_day: 1,

	// The list of the holidays.
	// Each holiday has a date definition and a name (in your language)
	// For instance:
	// holidays: {
	// 	'date': 'name',
	// 	'date': 'name',
	// 	...
	//   'date': 'name' //No ending comma for the last holiday
	// }
	// The format of the date may be one of the following:
	// # For a holiday recurring every year in the same day: 'dd-mm' (dd is the day of the month, mm is the month). For example: '25-12'.
	// # For a holiday that exists only in one specific year: 'dd-mm-yyyy' (dd is the day of the month, mm is the month, yyyy is the year). For example: '31-01-2013'
	// # For Easter: use simply 'easter'
	// # For holidays that are based on the Easter date: 'easter+offset in days'.
	//   Some examples:
	//   - 'easter-2' is Good Friday (2 days before Easter)
	//   - 'easter+1' is Easter Monday (1 day after Easter)
	//   - 'easter+39' is the Ascension Day
	//   - 'easter+49' is Pentecost
	// # For holidays that are on a specific weekday after the beginning of a month: 'mm+n*w', where 'mm' is the month, 'n' is the ordinal position, 'w' is the weekday being 0: Sunday, 1: Monday, ..., 6: Saturnday
	//   For example:
	//   - Second (2) Monday (1) in October (10): '10+2*1'
	// # For holidays that are on a specific weekday before the ending of a month: 'mm-n*w', where 'mm' is the month, 'n' is the ordinal position, 'w' is the weekday being 0: Sunday, 1: Monday, ..., 6: Saturnday
	//   For example:
	//   - Last (1) Saturnday (6) in Match (03): '03-1*6'
	//   - Last (1) Monday (1) in May (05): '05-1*1'
	// # You can also specify a holiday that lasts more than one day. To do that use the format 'start>end' where 'start' and 'end' are specified as above.
	//   For example:
	//   - From 1 January to 6 January: '01-01>06-01'
	//   - Easter and the day after Easter: 'easter>easter+1'
	//   Limitations: currently the multi-day holydays can't cross an year. So, for example, you can't specify a range as '30-12>01-01'; as a workaround you can specify two distinct holidays (for instance '30-12>31-12' and '01-01'). 
	holidays: {
    	'01-01':	'Újév',
        '15-03':	'1848-as forradalom',
        'easter-2': 'Nagypéntek',
		'easter+1': 'Húsvét hétfő',
		'easter+50':'Pünkösd hétfő',
		'01-05':    'A munka ünnepe',
		'20-08':    'Államalapítás',
		'23-10':    '1956-os forradalom',
		'01-11':    'Mindenszentek',
		'24-12':    'Szenteste',
		'25-12':    'Karácsony',
		'26-12':    'Karácsony'

	}
};