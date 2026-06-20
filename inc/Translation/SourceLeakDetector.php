<?php
/**
 * Source Leak Detector for translation validation.
 *
 * Detects when translated text retains substantial portions of the source
 * language, indicating a partial or failed translation.
 *
 * @package hollisho\translatepress\translate\deepseek\inc\Translation
 */

namespace hollisho\translatepress\translate\deepseek\inc\Translation;

/**
 * Class SourceLeakDetector
 *
 * Detects source language leakage in translated text by comparing
 * character sets, stopword overlap, and n-gram similarity.
 */
class SourceLeakDetector {

    /**
     * Minimum ratio of non-source characters required for non-Latin targets.
     * Below this threshold, the translation is considered to have source leak.
     *
     * @var float
     */
    const MIN_TARGET_SCRIPT_RATIO = 0.20;

    /**
     * Maximum ratio of Latin characters allowed in non-Latin target translations.
     * Above this threshold indicates source leak.
     *
     * @var float
     */
    const MAX_LATIN_RATIO_NON_LATIN_TARGET = 0.50;

    /**
     * Maximum similarity ratio between source and translation for Latin-script
     * language pairs. Above this threshold indicates the text was not translated.
     *
     * @var float
     */
    const MAX_SIMILARITY_RATIO = 0.60;

    /**
     * Minimum number of stopwords from source language found in translation
     * to trigger a source leak warning.
     *
     * @var int
     */
    const MAX_SOURCE_STOPWORDS = 3;

    /**
     * Known non-latin script language codes and their Unicode ranges.
     *
     * @var array
     */
    const NON_LATIN_SCRIPTS = [
        'ko' => ['name' => 'Korean', 'pattern' => '/[\x{AC00}-\x{D7AF}\x{1100}-\x{11FF}\x{3130}-\x{318F}]/u'],
        'ja' => ['name' => 'Japanese', 'pattern' => '/[\x{3040}-\x{309F}\x{30A0}-\x{30FF}\x{4E00}-\x{9FFF}]/u'],
        'zh' => ['name' => 'Chinese', 'pattern' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u'],
        'zh_CN' => ['name' => 'Chinese (Simplified)', 'pattern' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u'],
        'zh_TW' => ['name' => 'Chinese (Traditional)', 'pattern' => '/[\x{4E00}-\x{9FFF}\x{3400}-\x{4DBF}]/u'],
        'th' => ['name' => 'Thai', 'pattern' => '/[\x{0E00}-\x{0E7F}]/u'],
        'hi' => ['name' => 'Hindi', 'pattern' => '/[\x{0900}-\x{097F}]/u'],
        'ar' => ['name' => 'Arabic', 'pattern' => '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u'],
        'ru' => ['name' => 'Russian', 'pattern' => '/[\x{0400}-\x{04FF}]/u'],
        'uk' => ['name' => 'Ukrainian', 'pattern' => '/[\x{0400}-\x{04FF}]/u'],
        'bg' => ['name' => 'Bulgarian', 'pattern' => '/[\x{0400}-\x{04FF}]/u'],
        'el' => ['name' => 'Greek', 'pattern' => '/[\x{0370}-\x{03FF}]/u'],
        'he' => ['name' => 'Hebrew', 'pattern' => '/[\x{0590}-\x{05FF}]/u'],
    ];

    /**
     * Source language stopwords for leak detection.
     * These are stopwords that are DISTINCTIVE to the source language
     * and NOT shared with common target languages.
     *
     * @var array
     */
    const SOURCE_STOPWORDS = [
        'es' => ['el', 'la', 'los', 'las', 'del', 'al', 'él', 'ella', 'ellos', 'ellas', 'tú', 'mí', 'ti', 'suyo', 'mío', 'tuyo', 'nuestro', 'vuestro', 'suyos', 'míos', 'tuyos', 'nuestros', 'vuestros', 'nuestra', 'vuestra', 'nuestras', 'vuestras', 'fue', 'fuiste', 'fueron', 'ser', 'estar', 'hay', 'tiene', 'tienen', 'puede', 'pueden', 'hacer', 'hago', 'hace', 'hacen', 'sobre', 'entre', 'después', 'también', 'cuando', 'donde', 'porque', 'sin', 'hasta', 'desde', 'cada', 'otro', 'otra', 'otros', 'otras', 'muy', 'ya', 'sino', 'durante', 'antes', 'todo', 'toda', 'todos', 'todas', 'ese', 'esa', 'esos', 'esas', 'aquel', 'aquella', 'aquellos', 'aquellas', 'esto', 'eso', 'aquello', 'está', 'están', 'estoy', 'estás', 'estamos', 'estáis', 'están', 'tiene', 'tienes', 'tenemos', 'tenéis', 'tienen', 'puedo', 'puedes', 'podemos', 'podéis', 'pueden', 'debo', 'debes', 'debemos', 'debéis', 'deben', 'quiero', 'quieres', 'queremos', 'queréis', 'quieren', 'tengo', 'tienes', 'tenemos', 'tenéis', 'tienen', 'soy', 'eres', 'somos', 'sois', 'son', 'fui', 'fuiste', 'fuimos', 'fuisteis', 'fueron'],
        'en' => ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'be', 'been', 'being', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'shall', 'can', 'need', 'dare', 'ought', 'used', 'to', 'of', 'in', 'for', 'on', 'with', 'at', 'by', 'from', 'as', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'out', 'off', 'over', 'under', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'no', 'nor', 'not', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'just', 'because', 'but', 'and', 'or', 'if', 'while', 'that', 'this', 'these', 'those', 'i', 'you', 'he', 'she', 'it', 'we', 'they', 'me', 'him', 'her', 'us', 'them'],
        'pt' => ['o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas', 'com', 'por', 'para', 'que', 'é', 'são', 'como', 'mais', 'mas', 'seus', 'se', 'ao', 'lo', 'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas', 'eu', 'tu', 'ele', 'ela', 'nós', 'eles', 'elas', 'meu', 'teu', 'seu', 'nosso', 'foi', 'ser', 'estar', 'há', 'tem', 'pode', 'fazer', 'sobre', 'entre', 'depois', 'também', 'quando', 'onde', 'porque', 'sem', 'até', 'desde', 'cada', 'outro', 'outra', 'outros', 'outras', 'muito', 'já', 'senão', 'durante', 'antes', 'todo', 'toda', 'todos', 'todas'],
        'pt_BR' => ['o', 'a', 'os', 'as', 'um', 'uma', 'uns', 'umas', 'de', 'do', 'da', 'dos', 'das', 'em', 'no', 'na', 'nos', 'nas', 'com', 'por', 'para', 'que', 'é', 'são', 'como', 'mais', 'mas', 'seus', 'se', 'ao', 'lo', 'este', 'esta', 'estes', 'estas', 'esse', 'essa', 'esses', 'essas', 'eu', 'tu', 'ele', 'ela', 'nós', 'eles', 'elas', 'meu', 'teu', 'seu', 'nosso', 'foi', 'ser', 'estar', 'há', 'tem', 'pode', 'fazer', 'sobre', 'entre', 'depois', 'também', 'quando', 'onde', 'porque', 'sem', 'até', 'desde', 'cada', 'outro', 'outra', 'outros', 'outras', 'muito', 'já', 'senão', 'durante', 'antes', 'todo', 'toda', 'todos', 'todas'],
    ];

    /**
     * Spanish-exclusive stopwords that do NOT exist in Portuguese.
     * Used for es → pt_BR pair detection.
     *
     * @var array
     */
    const SPANISH_EXCLUSIVE_STOPWORDS = [
        'el', 'la', 'los', 'las', 'del', 'al',
        'él', 'ella', 'ellos', 'ellas',
        'tú', 'mí', 'ti',
        'suyo', 'suyos', 'suyas',
        'mío', 'míos', 'mía', 'mías',
        'tuyo', 'tuyos', 'tuya', 'tuyas',
        'nuestro', 'nuestros', 'nuestra', 'nuestras',
        'vuestro', 'vuestros', 'vuestra', 'vuestras',
        'está', 'están', 'estoy', 'estás', 'estamos', 'estáis',
        'tiene', 'tienes', 'tenemos', 'tenéis', 'tienen',
        'puedo', 'puedes', 'podemos', 'podéis', 'pueden',
        'debo', 'debes', 'debemos', 'debéis', 'deben',
        'quiero', 'quieres', 'queremos', 'queréis', 'quieren',
        'tengo', 'tienes', 'tenemos', 'tenéis', 'tienen',
        'soy', 'eres', 'somos', 'sois', 'son',
        'fui', 'fuiste', 'fuimos', 'fuisteis', 'fueron',
        'fuiste', 'fue', 'fuimos', 'fuisteis',
        'hago', 'haces', 'hacemos', 'hacéis', 'hacen',
        'aquel', 'aquella', 'aquellos', 'aquellas',
        'esto', 'eso', 'aquello',
        'sino', 'más', 'muy', 'ya',
        'después', 'antes', 'durante',
        'cuando', 'donde', 'porque',
        'sin', 'hasta', 'desde',
        'cada', 'otro', 'otra', 'otros', 'otras',
        'todo', 'toda', 'todos', 'todas',
        'ese', 'esa', 'esos', 'esas',
    ];

    /**
     * Spanish orthographic markers that indicate source leak.
     * These patterns are distinctive to Spanish and rarely appear in other languages.
     *
     * @var array
     */
    const SPANISH_ORTHOGRAPHIC_MARKERS = [
        '¿', '¡', 'ñ', 'Ñ',
        'ción', 'ciones', 'sión', 'siones',
        'mente', 'miento', 'mientos',
        'dad', 'dades', 'tad', 'tades',
        'ando', 'endo', 'iendo',
        'aba', 'aban', 'ía', 'ían',
        'aron', 'ieron', 'arán', 'erán', 'irán',
        'aría', 'ería', 'iría',
        'tienes', 'tiene', 'tienen', 'tenemos',
        'puedes', 'puede', 'pueden', 'podemos',
        'debes', 'debe', 'deben', 'debemos',
        'quieres', 'quiere', 'quieren', 'queremos',
        'haces', 'hace', 'hacen', 'hacemos',
        'estás', 'está', 'están', 'estamos',
        'eres', 'soy', 'somos', 'son',
        'fue', 'fuiste', 'fueron', 'fuimos',
    ];

    /**
     * Target language evidence markers.
     * For each target language, these words/patterns MUST appear in a valid translation
     * of a non-trivial text (50+ chars).
     *
     * @var array
     */
    const TARGET_LANGUAGE_EVIDENCE = [
        'pt_BR' => [
            'required_words' => ['você', 'não', 'com', 'para', 'como', 'mais', 'muito', 'pode', 'deve', 'isso', 'este', 'esta', 'esse', 'essa', 'aquele', 'aquela', 'meu', 'seu', 'nosso', 'seus', 'suas', 'minha', 'minhas', 'nossos', 'nossas', 'seus', 'suas'],
            'required_patterns' => ['ção', 'ções', 'lh', 'nh', 'ss', 'rr', 'ãe', 'ões', 'ã', 'õ', 'á', 'é', 'í', 'ó', 'ú', 'ê', 'ô'],
            'min_required_count' => 3,
        ],
        'en' => [
            'required_words' => ['the', 'a', 'an', 'is', 'are', 'was', 'were', 'you', 'your', 'can', 'will', 'have', 'has', 'this', 'that', 'these', 'those', 'not', 'but', 'and', 'or', 'for', 'with', 'from', 'about', 'into', 'through', 'during', 'before', 'after', 'above', 'below', 'between', 'under', 'over', 'again', 'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why', 'how', 'all', 'each', 'every', 'both', 'few', 'more', 'most', 'other', 'some', 'such', 'only', 'own', 'same', 'than', 'too', 'very', 'just', 'because', 'also', 'still', 'already', 'even', 'back', 'well', 'way', 'new', 'old', 'first', 'last', 'long', 'great', 'little', 'right', 'left', 'big', 'small', 'large', 'next', 'early', 'young', 'important', 'public', 'bad', 'good', 'different', 'possible', 'free', 'true', 'real', 'full', 'able', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten'],
            'required_patterns' => ['th', 'sh', 'ch', 'wh', 'ph', 'gh', 'ck', 'ng', 'nk', 'tion', 'sion', 'ment', 'ness', 'less', 'ful', 'able', 'ible', 'ous', 'ive', 'al', 'er', 'or', 'ist', 'ism', 'ize', 'ise', 'ify', 'en', 'ly', 'ward', 'wise', 'wards'],
            'min_required_count' => 5,
        ],
        'it' => [
            'required_words' => ['il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'del', 'dello', 'della', 'dei', 'degli', 'delle', 'nel', 'nello', 'nella', 'nei', 'negli', 'nelle', 'al', 'allo', 'alla', 'ai', 'agli', 'alle', 'di', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra', 'che', 'è', 'sono', 'come', 'più', 'ma', 'suo', 'sua', 'suoi', 'sue', 'nostro', 'nostra', 'nostri', 'nostre', 'loro', 'questo', 'questa', 'questi', 'queste', 'quello', 'quella', 'quelli', 'quelle', 'non', 'anche', 'già', 'ancora', 'sempre', 'mai', 'molto', 'tutto', 'tutti', 'ogni', 'altro', 'altra', 'altri', 'altre'],
            'required_patterns' => ['zione', 'zioni', 'mento', 'menti', 'tore', 'tori', 'trice', 'trici', 'abile', 'ibile', 'oso', 'osa', 'osi', 'ose', 'ico', 'ica', 'ici', 'iche', 'ismo', 'ismi', 'ista', 'iste', 'isti', 'mente', 'issimo', 'issima', 'issimi', 'issime', 'etto', 'etta', 'etti', 'ette', 'ello', 'ella', 'elli', 'elle', 'uccio', 'uccia', 'ucci', 'uccie', 'otto', 'otta', 'otti', 'otte'],
            'min_required_count' => 3,
        ],
        'fr' => [
            'required_words' => ['le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'au', 'aux', 'ce', 'cette', 'ces', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'nos', 'votre', 'vos', 'leur', 'leurs', 'je', 'tu', 'il', 'elle', 'nous', 'vous', 'ils', 'elles', 'est', 'sont', 'a', 'ont', 'fait', 'être', 'avoir', 'faire', 'dire', 'aller', 'voir', 'savoir', 'pouvoir', 'vouloir', 'venir', 'devoir', 'prendre', 'trouver', 'donner', 'parler', 'mettre', 'passer', 'regarder', 'aimer', 'croire', 'demander', 'rester', 'répondre', 'entendre', 'penser', 'arriver', 'connaître', 'sembler', 'tenir', 'porter', 'montrer', 'continuer', 'penser', 'suivre', 'comprendre', 'rendre', 'attendre', 'vivre', 'chercher', 'sortir', 'comprendre', 'appeler', 'tomber', 'revenir', 'entrer', 'rappeler', 'changer', 'devenir', 'commencer', 'mourir', 'ouvrir', 'marcher', 'perdre', 'arrêter', 'écrire', 'expliquer', 'lever', 'permettre', 'asseoir', 'lire', 'écrire', 'servir', 'apparaître', 'recevoir', 'répondre', 'descendre', 'ajouter', 'agir', 'adresser', 'approcher', 'brûler', 'cacher', 'casser', 'cesser', 'charger', 'choisir', 'conduire', 'construire', 'courir', 'couvrir', 'craindre', 'crier', 'décider', 'défendre', 'décrire', 'détruire', 'dormir', 'effacer', 'empêcher', 'enlever', 'entourer', 'envoyer', 'espérer', 'essayer', 'éviter', 'exister', 'exprimer', 'fermer', 'ficher', 'finir', 'forcer', 'gagner', 'garder', 'glisser', 'habiter', 'ignorer', 'imaginer', 'importer', 'indiquer', 'installer', 'intéresser', 'inviter', 'jeter', 'jouer', 'laisser', 'lancer', 'lever', 'maintenir', 'manger', 'manquer', 'marquer', 'mener', 'mentir', 'monter', 'mourir', 'naître', 'obliger', 'occuper', 'offrir', 'oublier', 'parer', 'parvenir', 'payer', 'pencher', 'penser', 'permettre', 'placer', 'plaire', 'porter', 'poser', 'pousser', 'pouvoir', 'préférer', 'prendre', 'préparer', 'présenter', 'produire', 'proposer', 'protéger', 'quitter', 'raconter', 'rappeler', 'recevoir', 'reconnaître', 'réfléchir', 'refuser', 'regarder', 'rejoindre', 'remarquer', 'remettre', 'remonter', 'rencontrer', 'rendre', 'rentrer', 'répéter', 'répondre', 'reposer', 'reprendre', 'ressembler', 'rester', 'retenir', 'retirer', 'retourner', 'retrouver', 'réussir', 'réveiller', 'revenir', 'rêver', 'revoir', 'rire', 'risquer', 'rouler', 'saisir', 'sauter', 'sauver', 'savoir', 'sembler', 'sentir', 'séparer', 'servir', 'sortir', 'souffrir', 'souhaiter', 'sourire', 'soutenir', 'souvenir', 'subir', 'suffire', 'suivre', 'supporter', 'supposer', 'surprendre', 'taire', 'tendre', 'tenir', 'tenter', 'terminer', 'tirer', 'tomber', 'toucher', 'tourner', 'traduire', 'traiter', 'travailler', 'traverser', 'tromper', 'trouver', 'tuer', 'utiliser', 'valoir', 'vendre', 'venir', 'vivre', 'voir', 'voler', 'vouloir'],
            'required_patterns' => ['tion', 'sion', 'ment', 'eux', 'euse', 'eur', 'rice', 'age', 'ure', 'ence', 'ance', 'té', 'ée', 'és', 'ées', 'er', 'ez', 'ais', 'ait', 'aient', 'ont', 'aient', 'issant', 'issant', 'ement', 'amment', 'emment', 'if', 'ive', 'aux', 'eau', 'ou', 'eu', 'oi', 'ui', 'ai', 'ei', 'au', 'eau', 'œu', 'æ'],
            'min_required_count' => 3,
        ],
        'de' => [
            'required_words' => ['der', 'die', 'das', 'ein', 'eine', 'einer', 'einem', 'einen', 'ich', 'du', 'er', 'sie', 'es', 'wir', 'ihr', 'sie', 'mich', 'mir', 'dir', 'ihm', 'uns', 'euch', 'ihnen', 'mein', 'meine', 'meiner', 'meinem', 'meinen', 'dein', 'deine', 'deiner', 'deinem', 'deinen', 'sein', 'seine', 'seiner', 'seinem', 'seinen', 'ihr', 'ihre', 'ihrer', 'ihrem', 'ihren', 'unser', 'unsere', 'unserer', 'unserem', 'unseren', 'euer', 'eure', 'eurer', 'eurem', 'euren', 'ist', 'sind', 'war', 'waren', 'hat', 'haben', 'hatte', 'hatten', 'wird', 'werden', 'wurde', 'wurden', 'kann', 'können', 'konnte', 'konnten', 'muss', 'müssen', 'musste', 'mussten', 'soll', 'sollen', 'sollte', 'sollten', 'will', 'wollen', 'wollte', 'wollten', 'darf', 'dürfen', 'durfte', 'durften', 'mag', 'mögen', 'mochte', 'mochten', 'nicht', 'kein', 'keine', 'keiner', 'keinem', 'keinen', 'auch', 'noch', 'schon', 'nur', 'noch', 'sehr', 'mehr', 'viel', 'wenig', 'alle', 'aller', 'alles', 'alle', 'jeder', 'jede', 'jedes', 'jedem', 'jeden', 'manche', 'manches', 'manchem', 'manchen', 'welcher', 'welche', 'welches', 'welchem', 'welchen', 'dieser', 'diese', 'dieses', 'diesem', 'diesen', 'jener', 'jene', 'jenes', 'jenem', 'jenen', 'solcher', 'solche', 'solches', 'solchem', 'solchen', 'was', 'wer', 'wo', 'wann', 'warum', 'wie', 'wohin', 'woher', 'ob', 'weil', 'wenn', 'als', 'obwohl', 'damit', 'dass', 'da', 'so', 'dann', 'danach', 'davor', 'danach', 'während', 'nach', 'vor', 'bei', 'mit', 'nach', 'seit', 'für', 'gegen', 'ohne', 'um', 'durch', 'über', 'unter', 'zwischen', 'hinter', 'neben', 'an', 'auf', 'in', 'von', 'zu', 'aus', 'nach', 'bei', 'mit', 'seit', 'für', 'gegen', 'ohne', 'um', 'durch', 'über', 'unter', 'zwischen', 'hinter', 'neben', 'an', 'auf', 'in', 'von', 'zu', 'aus'],
            'required_patterns' => ['ung', 'heit', 'keit', 'schaft', 'tion', 'sion', 'ismus', 'ist', 'isch', 'lich', 'isch', 'ig', 'bar', 'los', 'voll', 'sam', 'ern', 'eln', 'ieren', 'ieren', 'end', 'est', 'tet', 'ten', 'te', 'st', 'en', 'er', 'es', 'em', 'el', 'chen', 'lein', 'isch', 'ung', 'heit', 'keit', 'schaft', 'tion', 'sion', 'ismus', 'ist', 'isch', 'lich', 'isch', 'ig', 'bar', 'los', 'voll', 'sam', 'ern', 'eln', 'ieren', 'ieren', 'end', 'est', 'tet', 'ten', 'te', 'st', 'en', 'er', 'es', 'em', 'el', 'chen', 'lein', 'ß', 'ä', 'ö', 'ü'],
            'min_required_count' => 3,
        ],
        'tr' => [
            'required_words' => ['bir', 'bu', 'şu', 'o', 'ben', 'sen', 'biz', 'siz', 'onlar', 'benim', 'senin', 'onun', 'bizim', 'sizin', 'onların', 'bu', 'şu', 'o', 'hangi', 'nasıl', 'neden', 'niçin', 'nerede', 'nereden', 'nereye', 'ne', 'kim', 'ne zaman', 'niçin', 'ama', 'fakat', 'veya', 'ya da', 'ile', 'için', 'gibi', 'kadar', 'sonra', 'önce', 'üzere', 'rağmen', 'beri', 'itibaren', 'dolayı', 'değil', 'mi', 'mı', 'mu', 'mü', 'de', 'da', 'ki', 've', 'ama', 'fakat', 'lakin', 'yalnız', 'ancak', 'hem', 'hem de', 'ne', 'ne de', 'ya', 'ya da', 'veya', 'yahut'],
            'required_patterns' => ['lar', 'ler', 'da', 'de', 'dan', 'den', 'a', 'e', 'ı', 'i', 'o', 'u', 'ö', 'ü', 'ş', 'ç', 'ğ', 'ı', 'İ', 'Ş', 'Ç', 'Ğ', 'Ö', 'Ü'],
            'min_required_count' => 3,
        ],
    ];

    /**
     * Brand names and technical terms to ignore during leak detection.
     *
     * @var array
     */
    const IGNORED_TERMS = [
        'MasterTrend', 'Windows', 'BitLocker', 'EFS', 'Microsoft', 'OpenAI', 'DeepSeek',
        'TranslatePress', 'WordPress', 'PHP', 'API', 'URL', 'HTTP', 'HTTPS', 'HTML',
        'CSS', 'JSON', 'XML', 'SQL', 'USB', 'PC', 'CD', 'DVD', 'RAM', 'CPU', 'GPU',
        'SSD', 'HDD', 'BIOS', 'UEFI', 'GPT', 'MBR', 'NTFS', 'FAT32',
    ];

    /**
     * Check if a target language uses a non-Latin script.
     *
     * @param string $target_language The target language code.
     * @return bool
     */
    public static function is_non_latin_target( string $target_language ): bool {
        return isset( self::NON_LATIN_SCRIPTS[ $target_language ] );
    }

    /**
     * Detect source leak for a single translated string.
     *
     * @param string $source_text   The original source text.
     * @param string $translated    The translated text.
     * @param string $source_lang   Source language code.
     * @param string $target_lang   Target language code.
     *
     * @return array ['leak_detected' => bool, 'leak_ratio' => float, 'details' => string]
     */
    public function detect_leak( string $source_text, string $translated, string $source_lang, string $target_lang ): array {
        // Skip if translation is empty.
        if ( empty( trim( $translated ) ) ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => 1.0,
                'details'       => 'Translation is empty.',
            ];
        }

        // Skip if identical (already caught by validate_not_identical, but defensive).
        if ( $source_text === $translated ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => 1.0,
                'details'       => 'Translation is identical to source.',
            ];
        }

        // Remove ignored terms before analysis.
        $clean_source    = $this->remove_ignored_terms( $source_text );
        $clean_translated = $this->remove_ignored_terms( $translated );

        // Strategy 1: Non-Latin target script detection.
        if ( self::is_non_latin_target( $target_lang ) ) {
            return $this->detect_non_latin_leak( $clean_source, $clean_translated, $source_lang, $target_lang );
        }

        // Strategy 2: Latin-script similarity detection.
        return $this->detect_latin_leak( $clean_source, $clean_translated, $source_lang, $target_lang );
    }

    /**
     * Detect source leak for non-Latin target languages.
     *
     * For Korean, Japanese, Chinese, Thai, Hindi, Arabic, Russian, etc.,
     * the translation should contain a minimum ratio of target script characters.
     * If it's mostly Latin characters, the translation likely contains source leak.
     *
     * @param string $source    Cleaned source text.
     * @param string $translated Cleaned translated text.
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     *
     * @return array
     */
    private function detect_non_latin_leak( string $source, string $translated, string $source_lang, string $target_lang ): array {
        $script_info = self::NON_LATIN_SCRIPTS[ $target_lang ] ?? null;

        if ( ! $script_info ) {
            return [
                'leak_detected' => false,
                'leak_ratio'    => 0.0,
                'details'       => 'Unknown target script, skipping non-Latin check.',
            ];
        }

        // Count target script characters.
        preg_match_all( $script_info['pattern'], $translated, $target_matches );
        $target_char_count = count( $target_matches[0] );

        // Count Latin characters (a-z, A-Z).
        preg_match_all( '/[a-zA-Z]/', $translated, $latin_matches );
        $latin_char_count = count( $latin_matches[0] );

        // Count total alphabetic characters (target + Latin only, ignore digits/punctuation).
        $total_alpha = $target_char_count + $latin_char_count;

        if ( $total_alpha < 10 ) {
            return [
                'leak_detected' => false,
                'leak_ratio'    => 0.0,
                'details'       => 'Too few significant characters to analyze.',
            ];
        }

        $target_ratio = $target_char_count / $total_alpha;
        $latin_ratio  = $latin_char_count / $total_alpha;

        // Strategy 1: If Latin characters dominate (> 50%), it's likely source leak.
        if ( $latin_ratio > self::MAX_LATIN_RATIO_NON_LATIN_TARGET ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => $latin_ratio,
                'details'       => sprintf(
                    'Target %s: Latin ratio %.1f%% exceeds maximum %.1f%%. Target script ratio: %.1f%%.',
                    $script_info['name'],
                    $latin_ratio * 100,
                    self::MAX_LATIN_RATIO_NON_LATIN_TARGET * 100,
                    $target_ratio * 100
                ),
            ];
        }

        // Strategy 2: If target script ratio is below minimum AND there are significant Latin chars.
        if ( $target_ratio < self::MIN_TARGET_SCRIPT_RATIO && $latin_ratio > 0.20 ) {
            return [
                'leak_detected' => true,
                'leak_ratio'    => $latin_ratio,
                'details'       => sprintf(
                    'Target %s: script ratio %.1f%% below minimum %.1f%% with significant Latin %.1f%%.',
                    $script_info['name'],
                    $target_ratio * 100,
                    self::MIN_TARGET_SCRIPT_RATIO * 100,
                    $latin_ratio * 100
                ),
            ];
        }

        // Strategy 3: Check for long Latin word sequences (4+ consecutive Latin words).
        // This catches embedded Spanish/English sentences in non-Latin text.
        preg_match_all( '/[a-zA-Z]{3,}(?:\s+[a-zA-Z]{3,}){3,}/', $translated, $latin_sequences );
        if ( ! empty( $latin_sequences[0] ) ) {
            $longest_seq = max( array_map( 'strlen', $latin_sequences[0] ) );
            if ( $longest_seq > 20 ) {
                return [
                    'leak_detected' => true,
                    'leak_ratio'    => $latin_ratio,
                    'details'       => sprintf(
                        'Target %s: found Latin word sequence of %d chars (likely untranslated text).',
                        $script_info['name'],
                        $longest_seq
                    ),
                ];
            }
        }

        return [
            'leak_detected' => false,
            'leak_ratio'    => $latin_ratio,
            'details'       => sprintf(
                'Target %s: script ratio %.1f%%, Latin ratio %.1f%% — acceptable.',
                $script_info['name'],
                $target_ratio * 100,
                $latin_ratio * 100
            ),
        ];
    }

    /**
     * Detect source leak for Latin-script target languages.
     *
     * Uses per-pair language profiles with:
     * - Exclusive source stopwords (not shared with target)
     * - Target language evidence requirements
     * - Spanish orthographic markers
     * - N-gram similarity with pair-specific thresholds
     * - Long identical substring detection
     *
     * @param string $source     Cleaned source text.
     * @param string $translated Cleaned translated text.
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     *
     * @return array
     */
    private function detect_latin_leak( string $source, string $translated, string $source_lang, string $target_lang ): array {
        $leak_detected = false;
        $details       = [];
        $leak_signals  = 0;
        $max_signals   = 0;

        // ── Strategy 1: Exclusive source stopword detection ──
        $exclusive_result = $this->check_exclusive_stopword_leak( $translated, $source_lang, $target_lang );
        $max_signals++;
        if ( $exclusive_result['count'] >= 2 ) {
            $leak_signals++;
            $details[] = sprintf(
                'Found %d exclusive source stopwords: %s',
                $exclusive_result['count'],
                implode( ', ', array_slice( $exclusive_result['found'], 0, 5 ) )
            );
        }

        // ── Strategy 2: Spanish orthographic markers (es → any Latin) ──
        if ( $source_lang === 'es' ) {
            $max_signals++;
            $marker_result = $this->check_spanish_orthographic_markers( $translated );
            if ( $marker_result['count'] >= 2 ) {
                $leak_signals++;
                $details[] = sprintf(
                    'Found %d Spanish orthographic markers: %s',
                    $marker_result['count'],
                    implode( ', ', array_slice( $marker_result['found'], 0, 5 ) )
                );
            }
        }

        // ── Strategy 3: Target language evidence check ──
        $max_signals++;
        $evidence_result = $this->check_target_language_evidence( $translated, $target_lang, $source );
        if ( ! $evidence_result['has_evidence'] ) {
            $leak_signals++;
            $details[] = sprintf(
                'No evidence of target language %s in translation (checked %d required patterns).',
                $target_lang,
                $evidence_result['checked']
            );
        }

        // ── Strategy 4: N-gram similarity with pair-specific threshold ──
        $max_signals++;
        $ngram_similarity    = $this->calculate_ngram_similarity( $source, $translated );
        $ngram_threshold     = $this->get_ngram_threshold( $source_lang, $target_lang );
        if ( $ngram_similarity > $ngram_threshold ) {
            $leak_signals++;
            $details[] = sprintf(
                'N-gram similarity %.1f%% exceeds pair threshold %.1f%%.',
                $ngram_similarity * 100,
                $ngram_threshold * 100
            );
        }

        // ── Strategy 5: Long identical substring ──
        $max_signals++;
        $longest_match = $this->longest_common_substring_ratio( $source, $translated );
        if ( $longest_match > 0.50 ) {
            $leak_signals++;
            $details[] = sprintf(
                'Longest common substring ratio %.1f%% exceeds 50%%.',
                $longest_match * 100
            );
        }

        // ── Strategy 6: Near-identical check for long strings ──
        $max_signals++;
        if ( mb_strlen( $source, 'UTF-8' ) > 50 && mb_strlen( $translated, 'UTF-8' ) > 50 ) {
            $similarity = 0;
            similar_text( mb_strtolower( $source, 'UTF-8' ), mb_strtolower( $translated, 'UTF-8' ), $similarity );
            if ( $similarity > 85 ) {
                $leak_signals++;
                $details[] = sprintf(
                    'Near-identical text: %.1f%% similarity (threshold 85%%).',
                    $similarity
                );
            }
        }

        // ── Decision: leak if majority of signals triggered ──
        $leak_detected = $leak_signals >= 2;

        $max_ratio = max( $exclusive_result['ratio'], $ngram_similarity, $longest_match );

        return [
            'leak_detected' => $leak_detected,
            'leak_ratio'    => $max_ratio,
            'details'       => $leak_detected
                ? implode( ' | ', $details )
                : sprintf( 'Latin pair %s→%s: %d/%d leak signals — acceptable.', $source_lang, $target_lang, $leak_signals, $max_signals ),
        ];
    }

    /**
     * Check how many EXCLUSIVE source language stopwords appear in the translation.
     * Exclusive stopwords are words that exist in the source language but NOT
     * in the target language, making them strong leak indicators.
     *
     * @param string $translated   The translated text.
     * @param string $source_lang  Source language code.
     * @param string $target_lang  Target language code.
     *
     * @return array ['count' => int, 'ratio' => float, 'found' => array]
     */
    private function check_exclusive_stopword_leak( string $translated, string $source_lang, string $target_lang ): array {
        // Get exclusive stopwords for this pair.
        $exclusive = $this->get_exclusive_stopwords( $source_lang, $target_lang );

        if ( empty( $exclusive ) ) {
            return ['count' => 0, 'ratio' => 0.0, 'found' => []];
        }

        $translated_lower = mb_strtolower( $translated, 'UTF-8' );
        $found            = [];

        foreach ( $exclusive as $word ) {
            if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/ui', $translated_lower ) ) {
                $found[] = $word;
            }
        }

        $count = count( $found );

        return [
            'count' => $count,
            'ratio' => $count / count( $exclusive ),
            'found' => $found,
        ];
    }

    /**
     * Get exclusive stopwords for a source→target language pair.
     * These are words distinctive to the source that do NOT exist in the target.
     *
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     *
     * @return array List of exclusive stopwords.
     */
    private function get_exclusive_stopwords( string $source_lang, string $target_lang ): array {
        // Spanish → Portuguese: use pre-built exclusive list.
        if ( $source_lang === 'es' && ( $target_lang === 'pt' || $target_lang === 'pt_BR' || $target_lang === 'pt_PT' ) ) {
            return self::SPANISH_EXCLUSIVE_STOPWORDS;
        }

        // Spanish → Italian: words that are Spanish but not Italian.
        if ( $source_lang === 'es' && $target_lang === 'it' ) {
            return [
                'el', 'la', 'los', 'las', 'del', 'al',
                'él', 'ella', 'ellos', 'ellas',
                'tú', 'mí', 'ti',
                'suyo', 'suyos', 'suyas',
                'mío', 'míos', 'mía', 'mías',
                'tuyo', 'tuyos', 'tuya', 'tuyas',
                'nuestro', 'nuestros', 'nuestra', 'nuestras',
                'vuestro', 'vuestros', 'vuestra', 'vuestras',
                'está', 'están', 'estoy', 'estás', 'estamos', 'estáis',
                'tiene', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'puedo', 'puedes', 'podemos', 'podéis', 'pueden',
                'debo', 'debes', 'debemos', 'debéis', 'deben',
                'quiero', 'quieres', 'queremos', 'queréis', 'quieren',
                'tengo', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'soy', 'eres', 'somos', 'sois', 'son',
                'fui', 'fuiste', 'fuimos', 'fuisteis', 'fueron',
                'hago', 'haces', 'hacemos', 'hacéis', 'hacen',
                'aquel', 'aquella', 'aquellos', 'aquellas',
                'esto', 'eso', 'aquello',
                'sino', 'más', 'muy', 'ya',
                'después', 'antes', 'durante',
                'cuando', 'donde', 'porque',
                'sin', 'hasta', 'desde',
                'cada', 'otro', 'otra', 'otros', 'otras',
                'todo', 'toda', 'todos', 'todas',
                'ese', 'esa', 'esos', 'esas',
                'también', 'siempre', 'nunca', 'aquí', 'allí',
            ];
        }

        // Spanish → French: words that are Spanish but not French.
        if ( $source_lang === 'es' && ( $target_lang === 'fr' || $target_lang === 'fr_FR' || $target_lang === 'fr_CA' ) ) {
            return [
                'el', 'la', 'los', 'las', 'del', 'al',
                'él', 'ella', 'ellos', 'ellas',
                'tú', 'mí', 'ti',
                'suyo', 'suyos', 'suyas',
                'mío', 'míos', 'mía', 'mías',
                'tuyo', 'tuyos', 'tuya', 'tuyas',
                'nuestro', 'nuestros', 'nuestra', 'nuestras',
                'vuestro', 'vuestros', 'vuestra', 'vuestras',
                'está', 'están', 'estoy', 'estás', 'estamos', 'estáis',
                'tiene', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'puedo', 'puedes', 'podemos', 'podéis', 'pueden',
                'debo', 'debes', 'debemos', 'debéis', 'deben',
                'quiero', 'quieres', 'queremos', 'queréis', 'quieren',
                'tengo', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'soy', 'eres', 'somos', 'sois', 'son',
                'fui', 'fuiste', 'fuimos', 'fuisteis', 'fueron',
                'hago', 'haces', 'hacemos', 'hacéis', 'hacen',
                'aquel', 'aquella', 'aquellos', 'aquellas',
                'esto', 'eso', 'aquello',
                'sino', 'más', 'muy', 'ya',
                'después', 'antes', 'durante',
                'cuando', 'donde', 'porque',
                'sin', 'hasta', 'desde',
                'cada', 'otro', 'otra', 'otros', 'otras',
                'todo', 'toda', 'todos', 'todas',
                'ese', 'esa', 'esos', 'esas',
                'también', 'siempre', 'nunca', 'aquí', 'allí',
                'pero', 'como', 'más', 'muy', 'ya', 'sin', 'con',
            ];
        }

        // Spanish → English: Spanish words that are clearly not English.
        if ( $source_lang === 'es' && ( $target_lang === 'en' || $target_lang === 'en_US' || $target_lang === 'en_GB' ) ) {
            return [
                'él', 'ella', 'ellos', 'ellas',
                'tú', 'mí', 'ti',
                'suyo', 'suyos', 'suyas',
                'mío', 'míos', 'mía', 'mías',
                'tuyo', 'tuyos', 'tuya', 'tuyas',
                'nuestro', 'nuestros', 'nuestra', 'nuestras',
                'vuestro', 'vuestros', 'vuestra', 'vuestras',
                'está', 'están', 'estoy', 'estás', 'estamos', 'estáis',
                'tiene', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'puedo', 'puedes', 'podemos', 'podéis', 'pueden',
                'debo', 'debes', 'debemos', 'debéis', 'deben',
                'quiero', 'quieres', 'queremos', 'queréis', 'quieren',
                'tengo', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'soy', 'eres', 'somos', 'sois', 'son',
                'fui', 'fuiste', 'fuimos', 'fuisteis', 'fueron',
                'hago', 'haces', 'hacemos', 'hacéis', 'hacen',
                'aquel', 'aquella', 'aquellos', 'aquellas',
                'esto', 'eso', 'aquello',
                'sino', 'más', 'muy', 'ya',
                'después', 'antes', 'durante',
                'cuando', 'donde', 'porque',
                'sin', 'hasta', 'desde',
                'cada', 'otro', 'otra', 'otros', 'otras',
                'todo', 'toda', 'todos', 'todas',
                'ese', 'esa', 'esos', 'esas',
                'también', 'siempre', 'nunca', 'aquí', 'allí',
                'pero', 'como', 'sobre', 'entre', 'desde', 'hacia',
                'contraseña', 'recuperación', 'archivos', 'equipo',
                'pantalla', 'usuario', 'carpeta', 'respaldo',
            ];
        }

        // Spanish → German: Spanish words that are clearly not German.
        if ( $source_lang === 'es' && ( $target_lang === 'de' || $target_lang === 'de_DE' || $target_lang === 'de_AT' || $target_lang === 'de_CH' ) ) {
            return [
                'él', 'ella', 'ellos', 'ellas',
                'tú', 'mí', 'ti',
                'suyo', 'suyos', 'suyas',
                'mío', 'míos', 'mía', 'mías',
                'tuyo', 'tuyos', 'tuya', 'tuyas',
                'nuestro', 'nuestros', 'nuestra', 'nuestras',
                'vuestro', 'vuestros', 'vuestra', 'vuestras',
                'está', 'están', 'estoy', 'estás', 'estamos', 'estáis',
                'tiene', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'puedo', 'puedes', 'podemos', 'podéis', 'pueden',
                'debo', 'debes', 'debemos', 'debéis', 'deben',
                'quiero', 'quieres', 'queremos', 'queréis', 'quieren',
                'tengo', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'soy', 'eres', 'somos', 'sois', 'son',
                'fui', 'fuiste', 'fuimos', 'fuisteis', 'fueron',
                'hago', 'haces', 'hacemos', 'hacéis', 'hacen',
                'aquel', 'aquella', 'aquellos', 'aquellas',
                'esto', 'eso', 'aquello',
                'sino', 'más', 'muy', 'ya',
                'después', 'antes', 'durante',
                'cuando', 'donde', 'porque',
                'sin', 'hasta', 'desde',
                'cada', 'otro', 'otra', 'otros', 'otras',
                'todo', 'toda', 'todos', 'todas',
                'ese', 'esa', 'esos', 'esas',
                'también', 'siempre', 'nunca', 'aquí', 'allí',
                'pero', 'como', 'sobre', 'entre', 'desde', 'hacia',
                'el', 'la', 'los', 'las', 'del', 'al',
            ];
        }

        // Spanish → Turkish: Spanish words that are clearly not Turkish.
        if ( $source_lang === 'es' && ( $target_lang === 'tr' || $target_lang === 'tr_TR' ) ) {
            return [
                'él', 'ella', 'ellos', 'ellas',
                'tú', 'mí', 'ti',
                'suyo', 'suyos', 'suyas',
                'mío', 'míos', 'mía', 'mías',
                'tuyo', 'tuyos', 'tuya', 'tuyas',
                'nuestro', 'nuestros', 'nuestra', 'nuestras',
                'vuestro', 'vuestros', 'vuestra', 'vuestras',
                'está', 'están', 'estoy', 'estás', 'estamos', 'estáis',
                'tiene', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'puedo', 'puedes', 'podemos', 'podéis', 'pueden',
                'debo', 'debes', 'debemos', 'debéis', 'deben',
                'quiero', 'quieres', 'queremos', 'queréis', 'quieren',
                'tengo', 'tienes', 'tenemos', 'tenéis', 'tienen',
                'soy', 'eres', 'somos', 'sois', 'son',
                'fui', 'fuiste', 'fuimos', 'fuisteis', 'fueron',
                'hago', 'haces', 'hacemos', 'hacéis', 'hacen',
                'aquel', 'aquella', 'aquellos', 'aquellas',
                'esto', 'eso', 'aquello',
                'sino', 'más', 'muy', 'ya',
                'después', 'antes', 'durante',
                'cuando', 'donde', 'porque',
                'sin', 'hasta', 'desde',
                'cada', 'otro', 'otra', 'otros', 'otras',
                'todo', 'toda', 'todos', 'todas',
                'ese', 'esa', 'esos', 'esas',
                'también', 'siempre', 'nunca', 'aquí', 'allí',
                'pero', 'como', 'sobre', 'entre', 'desde', 'hacia',
                'el', 'la', 'los', 'las', 'del', 'al',
                'contraseña', 'recuperación', 'archivos', 'equipo',
                'pantalla', 'usuario', 'carpeta', 'respaldo',
            ];
        }

        // English → Spanish: English words that are clearly not Spanish.
        if ( $source_lang === 'en' && ( $target_lang === 'es' || $target_lang === 'es_ES' || $target_lang === 'es_MX' || $target_lang === 'es_AR' ) ) {
            return [
                'the', 'is', 'are', 'was', 'were', 'has', 'have', 'had',
                'will', 'would', 'could', 'should', 'might', 'shall',
                'you', 'your', 'they', 'their', 'them',
                'this', 'that', 'these', 'those',
                'what', 'which', 'who', 'whom',
                'password', 'recovery', 'files', 'computer', 'screen',
                'user', 'folder', 'backup', 'reset', 'change',
            ];
        }

        // Default: use generic source stopwords (less precise but better than nothing).
        return self::SOURCE_STOPWORDS[ $source_lang ] ?? [];
    }

    /**
     * Check for Spanish orthographic markers in translated text.
     * These markers (¿, ¡, ñ, ción, etc.) are strong indicators of
     * untranslated Spanish text.
     *
     * @param string $translated The translated text.
     *
     * @return array ['count' => int, 'found' => array]
     */
    private function check_spanish_orthographic_markers( string $translated ): array {
        $translated_lower = mb_strtolower( $translated, 'UTF-8' );
        $found            = [];

        foreach ( self::SPANISH_ORTHOGRAPHIC_MARKERS as $marker ) {
            if ( mb_strpos( $translated_lower, mb_strtolower( $marker, 'UTF-8' ), 0, 'UTF-8' ) !== false ) {
                $found[] = $marker;
            }
        }

        return [
            'count' => count( $found ),
            'found' => $found,
        ];
    }

    /**
     * Check if the translation contains evidence of the target language.
     * For each target language, verifies that required words/patterns appear.
     *
     * @param string $translated  The translated text.
     * @param string $target_lang Target language code.
     * @param string $source      Original source text (for length check).
     *
     * @return array ['has_evidence' => bool, 'checked' => int, 'found' => int]
     */
    private function check_target_language_evidence( string $translated, string $target_lang, string $source ): array {
        // Only check for non-trivial texts (50+ chars).
        if ( mb_strlen( $source, 'UTF-8' ) < 50 ) {
            return ['has_evidence' => true, 'checked' => 0, 'found' => 0];
        }

        $profile = self::TARGET_LANGUAGE_EVIDENCE[ $target_lang ] ?? null;

        if ( ! $profile ) {
            // No profile for this target — skip evidence check.
            return ['has_evidence' => true, 'checked' => 0, 'found' => 0];
        }

        $translated_lower = mb_strtolower( $translated, 'UTF-8' );
        $found_count      = 0;
        $checked          = 0;

        // Check required words.
        if ( ! empty( $profile['required_words'] ) ) {
            foreach ( $profile['required_words'] as $word ) {
                $checked++;
                if ( preg_match( '/\b' . preg_quote( $word, '/' ) . '\b/ui', $translated_lower ) ) {
                    $found_count++;
                }
            }
        }

        // Check required patterns.
        if ( ! empty( $profile['required_patterns'] ) ) {
            foreach ( $profile['required_patterns'] as $pattern ) {
                $checked++;
                if ( mb_strpos( $translated_lower, mb_strtolower( $pattern, 'UTF-8' ), 0, 'UTF-8' ) !== false ) {
                    $found_count++;
                }
            }
        }

        $min_required = $profile['min_required_count'] ?? 2;
        $has_evidence = $found_count >= $min_required;

        return [
            'has_evidence' => $has_evidence,
            'checked'      => $checked,
            'found'        => $found_count,
        ];
    }

    /**
     * Get the n-gram similarity threshold for a specific language pair.
     * Similar language pairs (es→pt_BR) need higher thresholds to avoid
     * false positives from shared vocabulary.
     *
     * @param string $source_lang Source language code.
     * @param string $target_lang Target language code.
     *
     * @return float Threshold (0.0 to 1.0).
     */
    private function get_ngram_threshold( string $source_lang, string $target_lang ): float {
        // Very similar language pairs — high threshold to avoid false positives.
        $similar_pairs = [
            'es_pt', 'es_pt_BR', 'es_pt_PT',
            'pt_es', 'pt_BR_es', 'pt_PT_es',
            'es_ca', 'ca_es', 'es_gl', 'gl_es',
            'it_fr', 'fr_it', 'fr_ca', 'ca_fr',
            'nl_de', 'de_nl', 'nl_af',
        ];

        $pair_key = $source_lang . '_' . $target_lang;

        if ( in_array( $pair_key, $similar_pairs, true ) ) {
            return 0.85; // 85% — very high, only flag near-identical text.
        }

        // Moderately similar pairs.
        $moderate_pairs = [
            'es_it', 'it_es', 'es_fr', 'fr_es',
            'es_de', 'de_es', 'en_de', 'de_en',
            'en_fr', 'fr_en', 'en_it', 'it_en',
        ];

        if ( in_array( $pair_key, $moderate_pairs, true ) ) {
            return 0.75; // 75%.
        }

        // Default for dissimilar pairs.
        return 0.60; // 60%.
    }

    /**
     * Calculate character-level bigram similarity between two strings.
     *
     * @param string $str1 First string.
     * @param string $str2 Second string.
     *
     * @return float Similarity ratio (0.0 to 1.0).
     */
    private function calculate_ngram_similarity( string $str1, string $str2 ): float {
        $str1 = mb_strtolower( trim( $str1 ), 'UTF-8' );
        $str2 = mb_strtolower( trim( $str2 ), 'UTF-8' );

        if ( empty( $str1 ) || empty( $str2 ) ) {
            return 0.0;
        }

        // For very long strings, sample to avoid excessive computation.
        if ( mb_strlen( $str1 ) > 2000 ) {
            $str1 = mb_substr( $str1, 0, 2000, 'UTF-8' );
        }
        if ( mb_strlen( $str2 ) > 2000 ) {
            $str2 = mb_substr( $str2, 0, 2000, 'UTF-8' );
        }

        $ngrams1 = $this->get_character_ngrams( $str1, 2 );
        $ngrams2 = $this->get_character_ngrams( $str2, 2 );

        if ( empty( $ngrams1 ) || empty( $ngrams2 ) ) {
            return 0.0;
        }

        $unique1      = array_unique( $ngrams1 );
        $unique2      = array_unique( $ngrams2 );
        $intersection = count( array_intersect( $unique1, $unique2 ) );
        $union        = count( array_unique( array_merge( $unique1, $unique2 ) ) );

        return $union > 0 ? $intersection / $union : 0.0;
    }

    /**
     * Generate character-level n-grams from a string.
     *
     * @param string $str The input string.
     * @param int    $n   The n-gram size.
     *
     * @return array
     */
    private function get_character_ngrams( string $str, int $n ): array {
        $len     = mb_strlen( $str, 'UTF-8' );
        $ngrams  = [];

        for ( $i = 0; $i <= $len - $n; $i++ ) {
            $ngrams[] = mb_substr( $str, $i, $n, 'UTF-8' );
        }

        return $ngrams;
    }

    /**
     * Calculate the ratio of the longest common substring to the shorter string.
     *
     * Uses a sampling approach for long strings to avoid O(n^2) complexity.
     *
     * @param string $str1 First string.
     * @param string $str2 Second string.
     *
     * @return float Ratio of longest common substring to shorter string length.
     */
    private function longest_common_substring_ratio( string $str1, string $str2 ): float {
        $str1 = mb_strtolower( trim( $str1 ), 'UTF-8' );
        $str2 = mb_strtolower( trim( $str2 ), 'UTF-8' );

        $len1 = mb_strlen( $str1, 'UTF-8' );
        $len2 = mb_strlen( $str2, 'UTF-8' );

        if ( $len1 === 0 || $len2 === 0 ) {
            return 0.0;
        }

        $shorter_len = min( $len1, $len2 );

        // For long strings, use sliding window approach.
        if ( $shorter_len > 500 ) {
            return $this->sampled_lcs_ratio( $str1, $str2 );
        }

        // For shorter strings, use direct comparison.
        $longest = 0;
        for ( $i = 0; $i < $len1; $i++ ) {
            for ( $j = 0; $j < $len2; $j++ ) {
                $k = 0;
                while (
                    $i + $k < $len1 &&
                    $j + $k < $len2 &&
                    mb_substr( $str1, $i + $k, 1, 'UTF-8' ) === mb_substr( $str2, $j + $k, 1, 'UTF-8' )
                ) {
                    $k++;
                }
                $longest = max( $longest, $k );
            }
        }

        return $longest / $shorter_len;
    }

    /**
     * Sampled LCS ratio for long strings.
     *
     * Checks fixed-length windows for exact matches.
     *
     * @param string $str1 First string.
     * @param string $str2 Second string.
     *
     * @return float
     */
    private function sampled_lcs_ratio( string $str1, string $str2 ): float {
        $window_size = 20;
        $len1        = mb_strlen( $str1, 'UTF-8' );
        $len2        = mb_strlen( $str2, 'UTF-8' );
        $shorter     = min( $len1, $len2 );

        // Build set of windows from str2.
        $windows = [];
        for ( $j = 0; $j <= $len2 - $window_size; $j += $window_size ) {
            $windows[] = mb_substr( $str2, $j, $window_size, 'UTF-8' );
        }

        if ( empty( $windows ) ) {
            return 0.0;
        }

        $matched_windows = 0;
        $total_checked   = 0;

        for ( $i = 0; $i <= $len1 - $window_size; $i += $window_size ) {
            $window = mb_substr( $str1, $i, $window_size, 'UTF-8' );
            $total_checked++;
            if ( in_array( $window, $windows, true ) ) {
                $matched_windows++;
            }
        }

        return $total_checked > 0 ? ( $matched_windows * $window_size ) / $shorter : 0.0;
    }

    /**
     * Remove ignored terms (brands, technical terms) from text before analysis.
     *
     * @param string $text The text to clean.
     * @return string Cleaned text.
     */
    private function remove_ignored_terms( string $text ): string {
        foreach ( self::IGNORED_TERMS as $term ) {
            $text = str_ireplace( $term, '', $text );
        }

        // Remove URLs.
        $text = preg_replace( '/https?:\/\/[^\s]+/', '', $text );

        // Remove file paths like C:\Windows\System32.
        $text = preg_replace( '/[A-Z]:\\\\[^\s]+/', '', $text );

        // Remove commands like net user, cd, dir.
        $text = preg_replace( '/\b(net\s+user|cd\s|dir\s)\b/i', '', $text );

        // Remove placeholders.
        $text = preg_replace( '/\{[a-zA-Z0-9_-]+\}/', '', $text );
        $text = preg_replace( '/\{\{[a-zA-Z0-9_-]+\}\}/', '', $text );
        $text = preg_replace( '/%[0-9]*\$?[sdfeEgGcxXobB%]/', '', $text );

        return $text;
    }

    /**
     * Batch-level leak detection.
     *
     * Analyzes an entire batch of translations for source leak patterns.
     *
     * @param array $input_strings  Original strings (keyed).
     * @param array $output_strings Translated strings (keyed).
     * @param string $source_lang   Source language code.
     * @param string $target_lang   Target language code.
     *
     * @return array ['leak_detected' => bool, 'leak_ratio' => float, 'leaking_keys' => array, 'details' => string]
     */
    public function detect_batch_leak( array $input_strings, array $output_strings, string $source_lang, string $target_lang ): array {
        $leaking_keys   = [];
        $total_leak_ratio = 0.0;
        $count          = 0;

        foreach ( $output_strings as $key => $translated ) {
            $source = $input_strings[ $key ] ?? '';
            $result = $this->detect_leak( $source, $translated, $source_lang, $target_lang );

            if ( $result['leak_detected'] ) {
                $leaking_keys[ $key ] = $result;
            }

            $total_leak_ratio += $result['leak_ratio'];
            $count++;
        }

        $avg_leak_ratio = $count > 0 ? $total_leak_ratio / $count : 0.0;
        $leak_percentage = count( $leaking_keys ) / max( $count, 1 );

        // Batch fails if more than 10% of items have source leak.
        $leak_detected = $leak_percentage > 0.10;

        return [
            'leak_detected' => $leak_detected,
            'leak_ratio'    => $avg_leak_ratio,
            'leaking_keys'  => $leaking_keys,
            'leak_percentage' => $leak_percentage,
            'details'       => $leak_detected
                ? sprintf( 'Source leak detected in %d/%d items (%.1f%%).', count( $leaking_keys ), $count, $leak_percentage * 100 )
                : 'No significant source leak detected in batch.',
        ];
    }
}
