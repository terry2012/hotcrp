<?php
// papercolumn.php -- HotCRP helper classes for paper list content
// HotCRP is Copyright (c) 2006-2015 Eddie Kohler and Regents of the UC
// Distributed under an MIT-like license; see LICENSE

class PaperColumnErrors {
    public $error_html = array();
    public $priority = null;
    public function add($error_html, $priority) {
        if ($this->priority === null || $this->priority < $priority) {
            $this->error_html = array();
            $this->priority = $priority;
        }
        if ($this->priority == $priority)
            $this->error_html[] = $error_html;
    }
}

class PaperColumn extends Column {
    static public $by_name = array();
    static public $factories = array();

    const PREP_SORT = -1;
    const PREP_FOLDED = 0; // value matters
    const PREP_VISIBLE = 1; // value matters
    const PREP_COMPLETION = 2;

    public function __construct($name, $flags, $extra = array()) {
        parent::__construct($name, $flags, $extra);
    }

    public static function lookup_local($name) {
        $lname = strtolower($name);
        return defval(self::$by_name, $lname, null);
    }

    public static function lookup($name, $errors = null) {
        $lname = strtolower($name);
        if (isset(self::$by_name[$lname]))
            return self::$by_name[$lname];
        foreach (self::$factories as $f)
            if (str_starts_with($lname, $f[0])
                && ($x = $f[1]->make_field($name, $errors)))
                return $x;
        return null;
    }

    public static function register($fdef) {
        $lname = strtolower($fdef->name);
        assert(!isset(self::$by_name[$lname]));
        self::$by_name[$lname] = $fdef;
        for ($i = 1; $i < func_num_args(); ++$i) {
            $lname = strtolower(func_get_arg($i));
            assert(!isset(self::$by_name[$lname]));
            self::$by_name[$lname] = $fdef;
        }
        return $fdef;
    }
    public static function register_factory($prefix, $f) {
        self::$factories[] = array(strtolower($prefix), $f);
    }
    public static function register_synonym($new_name, $old_name) {
        $fdef = self::$by_name[strtolower($old_name)];
        $new_name = strtolower($new_name);
        assert($fdef && !isset(self::$by_name[$new_name]));
        self::$by_name[$new_name] = $fdef;
    }

    public function prepare(PaperList $pl, $visible) {
        return true;
    }

    public function analyze($pl, &$rows) {
    }

    public function sort_prepare($pl, &$rows, $sorter) {
    }
    public function id_sorter($a, $b) {
        return $a->paperId - $b->paperId;
    }

    public function header($pl, $row, $ordinal) {
        return "&lt;" . htmlspecialchars($this->name) . "&gt;";
    }
    public function completion_name() {
        if ($this->completable)
            return $this->name;
        else
            return false;
    }
    public function completion_instances() {
        return array($this);
    }

    public function content_empty($pl, $row) {
        return false;
    }

    public function content($pl, $row, $rowidx) {
        return "";
    }
    public function text($pl, $row) {
        return "";
    }

    public function has_statistics() {
        return false;
    }
}

class IdPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("id", Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("minimal" => true, "sorter" => "id_sorter"));
    }
    public function header($pl, $row, $ordinal) {
        return "ID";
    }
    public function content($pl, $row, $rowidx) {
        $href = $pl->_paperLink($row);
        return "<a href=\"$href\" class=\"pnum taghl\" tabindex=\"4\">#$row->paperId</a>";
    }
    public function text($pl, $row) {
        return $row->paperId;
    }
}

class SelectorPaperColumn extends PaperColumn {
    public $is_selector = true;
    public function __construct($name, $extra) {
        parent::__construct($name, Column::VIEW_COLUMN, $extra);
    }
    public function prepare(PaperList $pl, $visible) {
        if ($this->name == "selconf" && !$pl->contact->privChair)
            return false;
        if ($this->name == "selconf" || $this->name == "selunlessconf")
            $pl->qopts["reviewer"] = $pl->reviewer_cid();
        if ($this->name == "selconf")
            $pl->add_footer_script("add_conflict_ajax()");
        return true;
    }
    public function header($pl, $row, $ordinal) {
        if ($this->name == "selconf")
            return "Conflict?";
        else
            return ($ordinal ? "&nbsp;" : "");
    }
    private function checked($pl, $row) {
        $def = ($this->name == "selon"
                || ($this->name == "selconf" && $row->reviewerConflictType > 0));
        return $pl->papersel ? defval($pl->papersel, $row->paperId, $def) : $def;
    }
    public function content($pl, $row, $rowidx) {
        if ($this->name == "selunlessconf" && $row->reviewerConflictType)
            return "";
        $pl->any->sel = true;
        $c = "";
        if ($this->checked($pl, $row)) {
            $c .= ' checked="checked"';
            unset($row->folded);
        }
        if ($this->name == "selconf" && $row->reviewerConflictType >= CONFLICT_AUTHOR)
            $c .= ' disabled="disabled"';
        if ($this->name != "selconf")
            $c .= ' onclick="rangeclick(event,this)"';
        return '<span class="pl_rownum fx6">' . $pl->count . '. </span>'
            . '<input type="checkbox" class="cb" name="pap[]" value="' . $row->paperId . '" tabindex="3" id="psel' . $pl->count . '" ' . $c . '/>';
    }
    public function text($pl, $row) {
        return $this->checked($pl, $row) ? "X" : "";
    }
}

class TitlePaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("title", Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("minimal" => true, "sorter" => "title_sorter"));
    }
    public function title_sorter($a, $b) {
        return strcasecmp($a->title, $b->title);
    }
    public function header($pl, $row, $ordinal) {
        return "Title";
    }
    public function content($pl, $row, $rowidx) {
        $href = $pl->_paperLink($row);
        $x = Text::highlight($row->title, defval($pl->search->matchPreg, "title"));
        return "<a href=\"$href\" class=\"ptitle taghl\" tabindex=\"5\">" . $x . "</a>" . $pl->_contentDownload($row);
    }
    public function text($pl, $row) {
        return $row->title;
    }
}

class StatusPaperColumn extends PaperColumn {
    private $is_long;
    public function __construct($name, $is_long, $extra = 0) {
        parent::__construct($name, Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("cssname" => "status", "sorter" => "status_sorter"));
        $this->is_long = $is_long;
    }
    public function sort_prepare($pl, &$rows, $sorter) {
        $force = $pl->search->limitName != "a" && $pl->contact->privChair;
        foreach ($rows as $row)
            $row->_status_sort_info = ($pl->contact->can_view_decision($row, $force) ? $row->outcome : -10000);
    }
    public function status_sorter($a, $b) {
        $x = $b->_status_sort_info - $a->_status_sort_info;
        $x = $x ? $x : ($a->timeWithdrawn > 0) - ($b->timeWithdrawn > 0);
        $x = $x ? $x : ($b->timeSubmitted > 0) - ($a->timeSubmitted > 0);
        return $x ? $x : ($b->paperStorageId > 1) - ($a->paperStorageId > 1);
    }
    public function header($pl, $row, $ordinal) {
        return "Status";
    }
    public function content($pl, $row, $rowidx) {
        if ($row->timeSubmitted <= 0 && $row->timeWithdrawn <= 0)
            $pl->any->need_submit = true;
        if ($row->outcome > 0 && $pl->contact->can_view_decision($row))
            $pl->any->accepted = true;
        if ($row->outcome > 0 && $row->timeFinalSubmitted <= 0
            && $pl->contact->can_view_decision($row))
            $pl->any->need_final = true;
        $status_info = $pl->contact->paper_status_info($row, $pl->search->limitName != "a" && $pl->contact->allow_administer($row));
        if (!$this->is_long && $status_info[0] == "pstat_sub")
            return "";
        return "<span class=\"pstat $status_info[0]\">" . htmlspecialchars($status_info[1]) . "</span>";
    }
    public function text($pl, $row) {
        $status_info = $pl->contact->paper_status_info($row, $pl->search->limitName != "a" && $pl->contact->allow_administer($row));
        return $status_info[1];
    }
}

class ReviewStatusPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("revstat", Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("sorter" => "review_status_sorter"));
    }
    public function prepare(PaperList $pl, $visible) {
        global $Conf;
        if ($pl->contact->is_reviewer()
            || $Conf->timeAuthorViewReviews()
            || $pl->contact->privChair) {
            $pl->qopts["startedReviewCount"] = true;
            return true;
        } else
            return false;
    }
    public function sort_prepare($pl, &$rows, $sorter) {
        foreach ($rows as $row) {
            if (!$pl->contact->can_count_review($row, null, null))
                $row->_review_status_sort_info = 2147483647;
            else
                $row->_review_status_sort_info = $row->num_reviews_submitted()
                    + $row->num_reviews_started($pl->contact) / 1000.0;
        }
    }
    public function review_status_sorter($a, $b) {
        $av = $a->_review_status_sort_info;
        $bv = $b->_review_status_sort_info;
        return ($av < $bv ? 1 : ($av == $bv ? 0 : -1));
    }
    public function header($pl, $row, $ordinal) {
        return '<span class="hottooltip" hottooltip="# completed reviews / # assigned reviews" hottooltipdir="b">#&nbsp;Reviews</span>';
    }
    public function content_empty($pl, $row) {
        return !$pl->contact->can_count_review($row, null, null);
    }
    public function content($pl, $row, $rowidx) {
        $done = $row->num_reviews_submitted();
        $started = $row->num_reviews_started($pl->contact);
        return "<b>$done</b>" . ($done == $started ? "" : "/$started");
    }
    public function text($pl, $row) {
        $done = $row->num_reviews_submitted();
        $started = $row->num_reviews_started($pl->contact);
        return $done . ($done == $started ? "" : "/$started");
    }
}

class AuthorsPaperColumn extends PaperColumn {
    private $aufull;
    public function __construct() {
        parent::__construct("authors", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function header($pl, $row, $ordinal) {
        return "Authors";
    }
    public function prepare(PaperList $pl, $visible) {
        $this->aufull = !$pl->is_folded("aufull");
        return true;
    }
    private function full_authors($row) {
        $lastaff = "";
        $anyaff = false;
        $aus = $affaus = array();
        foreach ($row->authorTable as $au) {
            if ($au[3] != $lastaff && count($aus)) {
                $affaus[] = array($aus, $lastaff);
                $aus = array();
                $anyaff = $anyaff || ($au[3] != "");
            }
            $lastaff = $au[3];
            $aus[] = $au[0] || $au[1] ? trim("$au[0] $au[1]") : $au[2];
        }
        if (count($aus))
            $affaus[] = array($aus, $lastaff);
        foreach ($affaus as &$ax)
            if ($ax[1] === "" && $anyaff)
                $ax[1] = "unaffiliated";
        return $affaus;
    }
    public function content_empty($pl, $row) {
        return !$pl->contact->can_view_authors($row, true);
    }
    public function content($pl, $row, $rowidx) {
        cleanAuthor($row);
        $aus = array();
        $highlight = defval($pl->search->matchPreg, "authorInformation", "");
        if ($this->aufull) {
            $affaus = $this->full_authors($row);
            foreach ($affaus as &$ax) {
                foreach ($ax[0] as &$axn)
                    $axn = Text::highlight($axn, $highlight);
                unset($axn);
                $aff = Text::highlight($ax[1], $highlight);
                $ax = commajoin($ax[0]) . ($aff ? " <span class='auaff'>($aff)</span>" : "");
            }
            return commajoin($affaus);
        } else if (!$highlight) {
            foreach ($row->authorTable as $au)
                $aus[] = Text::abbrevname_html($au);
            return join(", ", $aus);
        } else {
            foreach ($row->authorTable as $au) {
                $first = htmlspecialchars($au[0]);
                $x = Text::highlight(trim("$au[0] $au[1]"), $highlight, $nm);
                if ((!$nm || substr($x, 0, strlen($first)) == $first)
                    && ($initial = Text::initial($first)) != "")
                    $x = $initial . substr($x, strlen($first));
                $aus[] = $x;
            }
            return join(", ", $aus);
        }
    }
    public function text($pl, $row) {
        if (!$pl->contact->can_view_authors($row, true))
            return "";
        cleanAuthor($row);
        if ($this->aufull) {
            $affaus = $this->full_authors($row);
            foreach ($affaus as &$ax)
                $ax = commajoin($ax[0]) . ($ax[1] ? " ($ax[1])" : "");
            return commajoin($affaus);
        } else {
            $aus = array();
            foreach ($row->authorTable as $au)
                $aus[] = Text::abbrevname_text($au);
            return join(", ", $aus);
        }
    }
}

class CollabPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("collab", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        global $Conf;
        return !!$Conf->setting("sub_collab");
    }
    public function header($pl, $row, $ordinal) {
        return "Collaborators";
    }
    public function content_empty($pl, $row) {
        return ($row->collaborators == ""
                || strcasecmp($row->collaborators, "None") == 0
                || !$pl->contact->can_view_authors($row, true));
    }
    public function content($pl, $row, $rowidx) {
        $x = "";
        foreach (explode("\n", $row->collaborators) as $c)
            $x .= ($x === "" ? "" : ", ") . trim($c);
        return Text::highlight($x, defval($pl->search->matchPreg, "collaborators"));
    }
    public function text($pl, $row) {
        $x = "";
        foreach (explode("\n", $row->collaborators) as $c)
            $x .= ($x === "" ? "" : ", ") . trim($c);
        return $x;
    }
}

class AbstractPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("abstract", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function header($pl, $row, $ordinal) {
        return "Abstract";
    }
    public function content_empty($pl, $row) {
        return $row->abstract == "";
    }
    public function content($pl, $row, $rowidx) {
        return Text::highlight($row->abstract, defval($pl->search->matchPreg, "abstract"));
    }
    public function text($pl, $row) {
        return $row->abstract;
    }
}

class TopicListPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("topics", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        global $Conf;
        if (!$Conf->has_topics())
            return false;
        if ($visible)
            $pl->qopts["topics"] = 1;
        return true;
    }
    public function header($pl, $row, $ordinal) {
        return "Topics";
    }
    public function content_empty($pl, $row) {
        return !isset($row->topicIds) || $row->topicIds == "";
    }
    public function content($pl, $row, $rowidx) {
        return PaperInfo::unparse_topics($row->topicIds, @$row->topicInterest, true);
    }
}

class ReviewerTypePaperColumn extends PaperColumn {
    protected $xreviewer;
    public function __construct($name) {
        parent::__construct($name, Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("sorter" => "reviewer_type_sorter"));
    }
    public function analyze($pl, &$rows) {
        // PaperSearch is responsible for access control checking use of
        // `reviewerContact`, but we are careful anyway.
        if ($pl->search->reviewer_cid()
            && $pl->search->reviewer_cid() != $pl->contact->contactId
            && count($rows)) {
            $by_pid = array();
            foreach ($rows as $row)
                $by_pid[$row->paperId] = $row;
            $result = Dbl::qe_raw("select Paper.paperId, reviewType, reviewId, reviewModified, reviewSubmitted, reviewNeedsSubmit, reviewOrdinal, reviewBlind, PaperReview.contactId reviewContactId, requestedBy, reviewToken, reviewRound, conflictType from Paper left join PaperReview on (PaperReview.paperId=Paper.paperId and PaperReview.contactId=" . $pl->search->reviewer_cid() . ") left join PaperConflict on (PaperConflict.paperId=Paper.paperId and PaperConflict.contactId=" . $pl->search->reviewer_cid() . ") where Paper.paperId in (" . join(",", array_keys($by_pid)) . ") and (PaperReview.contactId is not null or PaperConflict.contactId is not null)");
            while (($xrow = edb_orow($result))) {
                $prow = $by_pid[$xrow->paperId];
                if ($pl->contact->allow_administer($prow)
                    || $pl->contact->can_view_review_identity($prow, $xrow, true)
                    || ($pl->contact->privChair
                        && $xrow->conflictType > 0
                        && !$xrow->reviewType))
                    $prow->_xreviewer = $xrow;
            }
            $this->xreviewer = $pl->search->reviewer();
        } else
            $this->xreviewer = false;
    }
    public function sort_prepare($pl, &$rows, $sorter) {
        if (!$this->xreviewer) {
            foreach ($rows as $row) {
                $row->_reviewer_type_sort_info = 2 * $row->reviewType;
                if (!$row->_reviewer_type_sort_info && $row->conflictType)
                    $row->_reviewer_type_sort_info = -$row->conflictType;
                else if ($row->reviewType > 0 && !$row->reviewSubmitted)
                    $row->_reviewer_type_sort_info += 1;
            }
        } else {
            foreach ($rows as $row)
                if (isset($row->_xreviewer)) {
                    $row->_reviewer_type_sort_info = 2 * $row->_xreviewer->reviewType;
                    if (!$row->_xreviewer->reviewSubmitted)
                        $row->_reviewer_type_sort_info += 1;
                } else
                    $row->_reviewer_type_sort_info = 0;
        }
    }
    public function reviewer_type_sorter($a, $b) {
        return $b->_reviewer_type_sort_info - $a->_reviewer_type_sort_info;
    }
    public function header($pl, $row, $ordinal) {
        if ($this->xreviewer)
            return Text::name_html($this->xreviewer) . "<br />Review</span>";
        else
            return "Review";
    }
    public function content($pl, $row, $rowidx) {
        if ($this->xreviewer && !isset($row->_xreviewer))
            $xrow = (object) array("reviewType" => 0, "conflictType" => 0);
        else if ($this->xreviewer)
            $xrow = $row->_xreviewer;
        else
            $xrow = $row;
        if ($xrow->reviewType) {
            $ranal = $pl->_reviewAnalysis($xrow);
            if ($ranal->needsSubmit)
                $pl->any->need_review = true;
            $t = PaperList::_reviewIcon($xrow, $ranal, true);
            if ($ranal->round)
                $t = "<div class='pl_revtype_round'>" . $t . "</div>";
        } else if ($xrow->conflictType > 0)
            $t = review_type_icon(-1);
        else
            $t = "";
        $x = null;
        if (!$this->xreviewer && $row->leadContactId && $row->leadContactId == $pl->contact->contactId)
            $x[] = '<span class="rtlead" title="Lead"><span class="rti">L</div></div>';
        if (!$this->xreviewer && $row->shepherdContactId && $row->shepherdContactId == $pl->contact->contactId)
            $x[] = '<span class="rtshep" title="Shepherd"><span class="rti">S</div></div>';
        if ($x)
            $t .= ($t ? '&nbsp;' : '') . join('&nbsp;', $x);
        return $t;
    }
}

class ReviewSubmittedPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("revsubmitted", Column::VIEW_COLUMN | Column::COMPLETABLE, array("cssname" => "text"));
    }
    public function prepare(PaperList $pl, $visible) {
        return !!$pl->contact->isPC;
    }
    public function header($pl, $row, $ordinal) {
        return "Review status";
    }
    public function content_empty($pl, $row) {
        return !$row->reviewId;
    }
    public function content($pl, $row, $rowidx) {
        if (!$row->reviewId)
            return "";
        $ranal = $pl->_reviewAnalysis($row);
        if ($ranal->needsSubmit)
            $pl->any->need_review = true;
        $t = $ranal->completion;
        if ($ranal->needsSubmit && !$ranal->delegated)
            $t = "<strong class='overdue'>$t</strong>";
        if (!$ranal->needsSubmit)
            $t = $ranal->link1 . $t . $ranal->link2;
        return $t;
    }
}

class ReviewDelegationPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("revdelegation", Column::VIEW_COLUMN,
                            array("cssname" => "text",
                                  "sorter" => "review_delegation_sorter"));
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->isPC)
            return false;
        $pl->qopts["reviewerName"] = true;
        $pl->qopts["allReviewScores"] = true;
        $pl->qopts["reviewLimitSql"] = "PaperReview.requestedBy=" . $pl->reviewer_cid();
        return true;
    }
    public function review_delegation_sorter($a, $b) {
        $x = strcasecmp($a->reviewLastName, $b->reviewLastName);
        $x = $x ? $x : strcasecmp($a->reviewFirstName, $b->reviewFirstName);
        return $x ? $x : strcasecmp($a->reviewEmail, $b->reviewEmail);
    }
    public function header($pl, $row, $ordinal) {
        return "Reviewer";
    }
    public function content($pl, $row, $rowidx) {
        global $Conf;
        $t = Text::user_html($row->reviewFirstName, $row->reviewLastName, $row->reviewEmail) . "<br /><small>Last login: ";
        return $t . ($row->reviewLastLogin ? $Conf->printableTimeShort($row->reviewLastLogin) : "Never") . "</small>";
    }
}

class AssignReviewPaperColumn extends ReviewerTypePaperColumn {
    public function __construct() {
        parent::__construct("assrev");
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->is_manager())
            return false;
        if ($visible > 0)
            $pl->add_footer_script("add_assrev_ajax()");
        $pl->qopts["reviewer"] = $pl->reviewer_cid();
        return true;
    }
    public function analyze($pl, &$rows) {
        $this->xreviewer = false;
    }
    public function header($pl, $row, $ordinal) {
        return "Assignment";
    }
    public function content_empty($pl, $row) {
        return !$pl->contact->allow_administer($row);
    }
    public function content($pl, $row, $rowidx) {
        if ($row->reviewerConflictType >= CONFLICT_AUTHOR)
            return '<span class="author">Author</span>';
        $rt = ($row->reviewerConflictType > 0 ? -1 : min(max($row->reviewerReviewType, 0), REVIEW_PRIMARY));
        if ($pl->reviewer_contact()->can_accept_review_assignment_ignore_conflict($row)
            || $rt > 0)
            $options = array(0 => "None",
                             REVIEW_PRIMARY => "Primary",
                             REVIEW_SECONDARY => "Secondary",
                             REVIEW_PC => "Optional",
                             -1 => "Conflict");
        else
            $options = array(0 => "None", -1 => "Conflict");
        return Ht::select("assrev$row->paperId", $options, $rt,
                           array("tabindex" => 3,
                                 "onchange" => "hiliter(this)"));
    }
}

class DesirabilityPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("desirability", Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("sorter" => "desirability_sorter"));
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->privChair)
            return false;
        if ($visible)
            $pl->qopts["desirability"] = 1;
        return true;
    }
    public function desirability_sorter($a, $b) {
        return $b->desirability - $a->desirability;
    }
    public function header($pl, $row, $ordinal) {
        return "Desirability";
    }
    public function content($pl, $row, $rowidx) {
        return htmlspecialchars(@($row->desirability + 0));
    }
    public function text($pl, $row) {
        return @($row->desirability + 0);
    }
}

class TopicScorePaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("topicscore", Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("sorter" => "topic_score_sorter"));
    }
    public function prepare(PaperList $pl, $visible) {
        global $Conf;
        if (!$Conf->has_topics() || !$pl->contact->isPC)
            return false;
        if ($visible) {
            $pl->qopts["reviewer"] = $pl->reviewer_cid();
            $pl->qopts["topicInterestScore"] = 1;
        }
        return true;
    }
    public function topic_score_sorter($a, $b) {
        return $b->topicInterestScore - $a->topicInterestScore;
    }
    public function header($pl, $row, $ordinal) {
        return "Topic<br/>score";
    }
    public function content($pl, $row, $rowidx) {
        return htmlspecialchars($row->topicInterestScore + 0);
    }
    public function text($pl, $row) {
        return $row->topicInterestScore + 0;
    }
}

class PreferencePaperColumn extends PaperColumn {
    private $editable;
    public function __construct($name, $editable) {
        parent::__construct($name, Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("sorter" => "preference_sorter"));
        $this->editable = $editable;
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->isPC)
            return false;
        if ($visible) {
            $pl->qopts["reviewerPreference"] = $pl->qopts["topicInterestScore"] = 1;
            $pl->qopts["reviewer"] = $pl->reviewer_cid();
        }
        if ($this->editable && $visible > 0) {
            $arg = "ajax=1&amp;setrevpref=1";
            if ($pl->contact->privChair && $pl->reviewer_cid())
                $arg .= "&amp;reviewer=" . $pl->reviewer_cid();
            $pl->add_footer_script("add_revpref_ajax(" . json_encode(hoturl_post_raw("paper", $arg)) . ")");
        }
        return true;
    }
    public function preference_sorter($a, $b) {
        $x = $b->reviewerPreference - $a->reviewerPreference;
        return $x ? $x : $b->topicInterestScore - $a->topicInterestScore;
    }
    public function header($pl, $row, $ordinal) {
        return "Preference";
    }
    public function content($pl, $row, $rowidx) {
        $pref = unparse_preference($row);
        if ($pl->reviewer_cid()
            && $pl->reviewer_cid() != $pl->contact->contactId
            && !$pl->contact->allow_administer($row))
            return "N/A";
        else if (!$this->editable)
            return $pref;
        else if ($row->reviewerConflictType > 0)
            return "N/A";
        else
            return '<input id="revpref' . $row->paperId . '" name="revpref' . $row->paperId
                . '" value="' . $pref . '" type="text" size="4" tabindex="2" placeholder="0" />';
    }
    public function text($pl, $row) {
        return @($row->reviewerPreference + 0);
    }
}

class PreferenceListPaperColumn extends PaperColumn {
    private $topics;
    public function __construct($name, $topics) {
        $this->topics = $topics;
        parent::__construct($name, Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        global $Conf;
        if ($this->topics && !$Conf->has_topics())
            $this->topics = false;
        if (!$pl->contact->is_manager())
            return false;
        if ($visible) {
            $pl->qopts["allReviewerPreference"] = $pl->qopts["allConflictType"] = 1;
            if ($this->topics)
                $pl->qopts["topics"] = 1;
        }
        return true;
    }
    public function header($pl, $row, $ordinal) {
        return "Preferences";
    }
    public function content_empty($pl, $row) {
        return !$pl->contact->allow_administer($row);
    }
    public function content($pl, $row, $rowidx) {
        $prefs = $row->reviewer_preferences();
        $topics = $this->topics ? $row->topics() : false;
        $ts = array();
        if ($prefs || $topics)
            foreach (pcMembers() as $pcid => $pc) {
                $pref = @$prefs[$pcid] ? : array();
                if ($this->topics)
                    $pref[2] = $row->topic_interest_score($pc);
                if (($pspan = unparse_preference_span($pref)) !== "")
                    $ts[] = '<span class="nw">' . $pc->name_html() . $pspan . '</span>';
            }
        return join(", ", $ts);
    }
}

class ReviewerListPaperColumn extends PaperColumn {
    private $topics;
    public function __construct() {
        parent::__construct("reviewers", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        global $Conf;
        $this->topics = $Conf->has_topics();
        if (!$pl->contact->can_view_some_review_identity(null))
            return false;
        if ($visible) {
            $pl->qopts["reviewList"] = 1;
            if ($pl->contact->privChair)
                $pl->qopts["allReviewerPreference"] = $pl->qopts["topics"] = 1;
        }
        return true;
    }
    public function header($pl, $row, $ordinal) {
        return "Reviewers";
    }
    public function content($pl, $row, $rowidx) {
        // see also search.php > getaction == "reviewers"
        if (!isset($pl->review_list[$row->paperId]))
            return "";
        $prefs = $topics = false;
        if ($pl->contact->privChair) {
            $prefs = $row->reviewer_preferences();
            $topics = $this->topics ? $row->topics() : null;
            $pcm = pcMembers();
        }
        $x = array();
        foreach ($pl->review_list[$row->paperId] as $xrow)
            if ($xrow->lastName) {
                $ranal = $pl->_reviewAnalysis($xrow);
                $n = Text::name_html($xrow);
                if ($xrow->reviewType >= REVIEW_SECONDARY)
                    $n .= "&nbsp;" . PaperList::_reviewIcon($xrow, $ranal, false);
                if ($prefs || $topics) {
                    $pref = @$prefs[$xrow->contactId];
                    if ($topics)
                        $pref[2] = $row->topic_interest_score((int) $xrow->contactId);
                    $n .= unparse_preference_span($pref);
                }
                $x[] = '<span class="nw">' . $n . '</span>';
            }
        return $pl->maybeConflict($row, join(", ", $x),
                                  $pl->contact->can_view_review_identity($row, null, false));
    }
}

class PCConflictListPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("pcconf", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->privChair)
            return false;
        if ($visible)
            $pl->qopts["allConflictType"] = 1;
        return true;
    }
    public function header($pl, $row, $ordinal) {
        return "PC conflicts";
    }
    public function content($pl, $row, $rowidx) {
        $conf = $row->conflicts();
        $y = array();
        foreach (pcMembers() as $id => $pc)
            if (@$conf[$id])
                $y[] = $pc->name_html();
        return join(", ", $y);
    }
}

class ConflictMatchPaperColumn extends PaperColumn {
    private $field;
    public function __construct($name, $field) {
        parent::__construct($name, Column::VIEW_ROW);
        $this->field = $field;
    }
    public function prepare(PaperList $pl, $visible) {
        return $pl->contact->privChair;
    }
    public function header($pl, $row, $ordinal) {
        if ($this->field == "authorInformation")
            return "<strong>Potential conflict in authors</strong>";
        else
            return "<strong>Potential conflict in collaborators</strong>";
    }
    public function content_empty($pl, $row) {
        return defval($pl->search->matchPreg, $this->field, "") == "";
    }
    public function content($pl, $row, $rowidx) {
        $preg = defval($pl->search->matchPreg, $this->field, "");
        if ($preg == "")
            return "";
        $text = "";
        $field = $this->field;
        foreach (explode("\n", $row->$field) as $line)
            if (($line = trim($line)) != "") {
                $line = Text::highlight($line, $preg, $n);
                if ($n)
                    $text .= ($text ? "; " : "") . $line;
            }
        if ($text != "")
            unset($row->folded);
        return $text;
    }
}

class TagListPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("tags", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->can_view_tags(null))
            return false;
        if ($visible)
            $pl->qopts["tags"] = 1;
        return true;
    }
    public function header($pl, $row, $ordinal) {
        return "Tags";
    }
    public function content_empty($pl, $row) {
        return !$pl->contact->can_view_tags($row, true);
    }
    public function content($pl, $row, $rowidx) {
        if ((string) $row->paperTags === "")
            return "";
        $viewable = $pl->tagger->viewable($row->paperTags);
        $noconf = $row->conflictType <= 0;
        $str = $pl->tagger->unparse_and_link($viewable, $row->paperTags,
                                             $pl->search->highlight_tags(), $noconf);
        return $pl->maybeConflict($row, $str, $noconf || $pl->contact->can_view_tags($row, false));
    }
}

class TagPaperColumn extends PaperColumn {
    protected $is_value;
    protected $dtag;
    protected $ctag;
    protected $editable = false;
    static private $sortf_ctr = 0;
    public function __construct($name, $tag, $is_value) {
        parent::__construct($name, Column::VIEW_COLUMN | Column::COMPLETABLE, array("sorter" => "tag_sorter"));
        $this->dtag = $tag;
        $this->is_value = $is_value;
        $this->cssname = ($this->is_value ? "tagval" : "tag");
    }
    public function make_field($name, $errors) {
        $p = strpos($name, ":") ? : strpos($name, "#");
        return parent::register(new TagPaperColumn($name, substr($name, $p + 1), $this->is_value));
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->can_view_tags(null))
            return false;
        if (!$this->dtag && $visible === PaperColumn::PREP_COMPLETION)
            return true;
        $tagger = new Tagger($pl->contact);
        if (!($ctag = $tagger->check($this->dtag, Tagger::NOVALUE | Tagger::ALLOWCONTACTID)))
            return false;
        $this->ctag = strtolower(" $ctag#");
        if ($visible)
            $pl->qopts["tags"] = 1;
        return true;
    }
    public function completion_name() {
        return $this->dtag ? "#$this->dtag" : "#<tag>";
    }
    protected function _tag_value($row) {
        if (($p = strpos($row->paperTags, $this->ctag)) === false)
            return null;
        else
            return (int) substr($row->paperTags, $p + strlen($this->ctag));
    }
    public function sort_prepare($pl, &$rows, $sorter) {
        global $Conf;
        $sorter->sortf = $sortf = "_tag_sort_info." . self::$sortf_ctr;
        ++self::$sortf_ctr;
        $careful = !$pl->contact->privChair
            && $Conf->setting("tag_seeall") <= 0;
        $unviewable = $empty = $sorter->reverse ? -2147483647 : 2147483647;
        if ($this->editable)
            $empty = $sorter->reverse ? -2147483646 : 2147483646;
        foreach ($rows as $row)
            if ($careful && !$pl->contact->can_view_tags($row, true))
                $row->$sortf = $unviewable;
            else if (($row->$sortf = $this->_tag_value($row)) === null)
                $row->$sortf = $empty;
    }
    public function tag_sorter($a, $b, $sorter) {
        $sortf = $sorter->sortf;
        return $a->$sortf < $b->$sortf ? -1 :
            ($a->$sortf == $b->$sortf ? 0 : 1);
    }
    public function header($pl, $row, $ordinal) {
        return "#$this->dtag";
    }
    public function content_empty($pl, $row) {
        return !$pl->contact->can_view_tags($row, true);
    }
    public function content($pl, $row, $rowidx) {
        if (($v = $this->_tag_value($row)) === null)
            return "";
        else if ($v === 0 && !$this->is_value)
            return "&#x2713;";
        else
            return $v;
    }
    public function text($pl, $row) {
        if (($v = $this->_tag_value($row)) === null)
            return "";
        else if ($v === 0 && !$this->is_value)
            return "X";
        else
            return $v;
    }
}

class EditTagPaperColumn extends TagPaperColumn {
    public function __construct($name, $tag, $is_value) {
        parent::__construct($name, $tag, $is_value);
        $this->cssname = ($this->is_value ? "edittagval" : "edittag");
        $this->editable = true;
    }
    public function make_field($name, $errors) {
        $p = strpos($name, ":") ? : strpos($name, "#");
        return parent::register(new EditTagPaperColumn($name, substr($name, $p + 1), $this->is_value));
    }
    public function prepare(PaperList $pl, $visible) {
        if (($p = parent::prepare($pl, $visible)) && $visible > 0) {
            $pl->add_footer_html(
                 Ht::form(hoturl_post("paper", "settags=1&amp;forceShow=1"),
                           array("id" => "edittagajaxform",
                                 "style" => "display:none")) . "<div>"
                 . Ht::hidden("p") . Ht::hidden("addtags")
                 . Ht::hidden("deltags") . "</div></form>",
                 "edittagajaxform");
            $sorter = @$pl->sorters[0];
            if (("edit" . $sorter->type == $this->name
                 || $sorter->type == $this->name)
                && !$sorter->reverse
                && !$pl->search->thenmap
                && $this->is_value)
                $pl->add_footer_script("add_edittag_ajax('$this->dtag')");
            else
                $pl->add_footer_script("add_edittag_ajax()");
        }
        return $p;
    }
    public function content($pl, $row, $rowidx) {
        $v = $this->_tag_value($row);
        if (!$this->is_value)
            return "<input type='checkbox' class='cb' name='tag:$this->dtag $row->paperId' value='x' tabindex='6'"
                . ($v !== null ? " checked='checked'" : "") . " />";
        else
            return "<input type='text' size='4' name='tag:$this->dtag $row->paperId' value=\""
                . ($v !== null ? htmlspecialchars($v) : "") . "\" tabindex='6' />";
    }
}

class ScorePaperColumn extends PaperColumn {
    public $score;
    public $max_score;
    private $form_field;
    private static $registered = array();
    public function __construct($score) {
        parent::__construct($score, Column::VIEW_COLUMN | Column::FOLDABLE | Column::COMPLETABLE,
                            array("sorter" => "score_sorter"));
        $this->minimal = true;
        $this->cssname = "score";
        $this->score = $score;
    }
    public static function lookup_all() {
        $reg = array();
        foreach (ReviewForm::all_fields() as $f)
            if (($s = self::_make_field($f->id)))
                $reg[$f->display_order] = $s;
        ksort($reg);
        return $reg;
    }
    private static function _make_field($name) {
        if (($f = ReviewForm::field_search($name))
            && $f->has_options && $f->display_order !== false) {
            $s = parent::lookup_local($f->id);
            $s = $s ? : PaperColumn::register(new ScorePaperColumn($f->id));
            return $s;
        } else
            return null;
    }
    public function make_field($name, $errors) {
        return self::_make_field($name);
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->scoresOk)
            return false;
        $this->form_field = ReviewForm::field($this->score);
        if ($this->form_field->view_score <= $pl->contact->permissive_view_score_bound())
            return false;
        if ($visible) {
            $pl->qopts["scores"][$this->score] = true;
            $pl->qopts["need_javascript"] = true;
            $this->max_score = count($this->form_field->options);
        }
        return true;
    }
    public function sort_prepare($pl, &$rows, $sorter) {
        $this->_sortinfo = $sortinfo = "_score_sort_info." . $this->score . $sorter->score;
        $this->_avginfo = $avginfo = "_score_sort_avg." . $this->score;
        $reviewer = $pl->reviewer_cid();
        $field = $this->form_field;
        foreach ($rows as $row)
            if (($scores = $row->viewable_scores($field, $pl->contact, null)) !== null) {
                $scoreinfo = new ScoreInfo($scores);
                $row->$sortinfo = $scoreinfo->sort_data($sorter->score, $reviewer);
                $row->$avginfo = $scoreinfo->mean();
            } else {
                $row->$sortinfo = ScoreInfo::empty_sort_data($sorter->score);
                $row->$avginfo = -1;
            }
        $this->_textual_sort = ScoreInfo::sort_by_strcmp($sorter->score);
    }
    public function score_sorter($a, $b) {
        $sortinfo = $this->_sortinfo;
        if ($this->_textual_sort)
            $x = strcmp($b->$sortinfo, $a->$sortinfo);
        else
            $x = $b->$sortinfo - $a->$sortinfo;
        if (!$x) {
            $avginfo = $this->_avginfo;
            $x = $b->$avginfo - $a->$avginfo;
        }
        return $x < 0 ? -1 : ($x == 0 ? 0 : 1);
    }
    public function header($pl, $row, $ordinal) {
        return $this->form_field->web_abbreviation();
    }
    public function completion_name() {
        if ($this->score && ($ff = ReviewForm::field($this->score)))
            return $ff->abbreviation;
        else
            return null;
    }
    public function completion_instances() {
        return self::lookup_all();
    }
    public function content_empty($pl, $row) {
        // Do not use viewable_scores to determine content emptiness, since
        // that would load the scores from the DB -- even for folded score
        // columns.
        return !$row->may_have_viewable_scores($this->form_field, $pl->contact, true);
    }
    public function content($pl, $row, $rowidx) {
        $wrap_conflict = false;
        $scores = $row->viewable_scores($this->form_field, $pl->contact, false);
        if ($scores === null && $pl->contact->allow_administer($row)) {
            $wrap_conflict = true;
            $scores = $row->viewable_scores($this->form_field, $pl->contact, true);
        }
        if ($scores) {
            $t = $this->form_field->unparse_graph($scores, 1, defval($row, $this->score));
            if ($pl->live_table && $rowidx % 16 == 15)
                $t .= "<script>scorechart()</script>";
            return $wrap_conflict ? '<span class="fx5">' . $t . '</span>' : $t;
        }
        return "";
    }
}

class FormulaPaperColumn extends PaperColumn {
    private static $registered = array();
    public static $list = array();
    public $formula;
    private $formula_function;
    public $statistics;
    public function __construct($name, $formula) {
        parent::__construct(strtolower($name), Column::VIEW_COLUMN | Column::FOLDABLE | Column::COMPLETABLE,
                            array("minimal" => true, "sorter" => "formula_sorter"));
        $this->cssname = "formula";
        $this->formula = $formula;
        if ($formula && @$formula->formulaId)
            self::$list[$formula->formulaId] = $formula;
    }
    public static function lookup_all() {
        return self::$registered;
    }
    public static function register($fdef) {
        PaperColumn::register($fdef);
        self::$registered[] = $fdef;
    }
    public function make_field($name, $errors) {
        foreach (self::$registered as $col)
            if (strcasecmp($col->formula->name, $name) == 0)
                return $col;
        if (substr($name, 0, 4) === "edit")
            return null;
        $formula = new Formula($name);
        if (!$formula->check()) {
            if ($errors && strpos($name, "(") !== false)
                $errors->add($formula->error_html(), 1);
            return null;
        }
        $fdef = new FormulaPaperColumn("formulax" . (count(self::$registered) + 1), $formula);
        self::register($fdef);
        return $fdef;
    }
    public function completion_name() {
        if ($this->formula && $this->formula->name) {
            if (strpos($this->formula->name, " ") !== false)
                return "\"{$this->formula->name}\"";
            else
                return $this->formula->name;
        } else
            return "(<formula>)";
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$this->formula && $visible === PaperColumn::PREP_COMPLETION)
            return true;
        $view_bound = $pl->contact->permissive_view_score_bound();
        if ($pl->search->limitName == "a")
            $view_bound = max($view_bound, VIEWSCORE_AUTHOR - 1);
        if (!$pl->scoresOk
            || !$this->formula->check()
            || $this->formula->base_view_score() <= $view_bound)
            return false;
        $this->formula_function = $this->formula->compile_function($pl->contact);
        if ($visible)
            $this->formula->add_query_options($pl->qopts, $pl->contact);
        $this->statistics = new ScoreInfo;
        return true;
    }
    public function sort_prepare($pl, &$rows, $sorter) {
        $formulaf = $this->formula_function;
        $this->formula_sorter = $sorter = "_formula_sort_info." . $this->formula->name;
        foreach ($rows as $row)
            $row->$sorter = $formulaf($row, null, $pl->contact, "s");
    }
    public function formula_sorter($a, $b) {
        $sorter = $this->formula_sorter;
        $as = $a->$sorter;
        $bs = $b->$sorter;
        if ($as === null || $bs === null)
            return $as === $bs ? 0 : ($as === null ? -1 : 1);
        else
            return $as == $bs ? 0 : ($as < $bs ? -1 : 1);
    }
    public function header($pl, $row, $ordinal) {
        $x = $this->formula->column_header();
        if ($this->formula->headingTitle
            && $this->formula->headingTitle != $x)
            return "<span class=\"hottooltip\" hottooltip=\"" . htmlspecialchars($this->formula->headingTitle) . "\">" . htmlspecialchars($x) . "</span>";
        else
            return htmlspecialchars($x);
    }
    public function content($pl, $row, $rowidx) {
        $formulaf = $this->formula_function;
        $s = $formulaf($row, null, $pl->contact);
        $t = $this->formula->unparse_html($s);
        if ($row->conflictType > 0 && $pl->contact->allow_administer($row)) {
            $ss = $formulaf($row, null, $pl->contact, null, true);
            $tt = $this->formula->unparse_html($ss);
            $this->statistics->add($ss);
            return '<span class="fn5">' . $t . '</span><span class="fx5">' . $tt . '</span>';
        } else {
            $this->statistics->add($s);
            return $t;
        }
    }
    public function text($pl, $row) {
        $formulaf = $this->formula_function;
        $s = $formulaf($row, null, $pl->contact);
        return $this->formula->unparse_text($s);
    }
    public function has_statistics() {
        return $this->statistics && $this->statistics->count();
    }
    public function statistic($what) {
        return $this->formula->unparse_html($this->statistics->statistic($what));
    }
}

class TagReportPaperColumn extends PaperColumn {
    private static $registered = array();
    public function __construct($tag) {
        parent::__construct("tagrep_" . preg_replace('/\W+/', '_', $tag),
                            Column::VIEW_ROW | Column::FOLDABLE);
        $this->cssname = "tagrep";
        $this->tag = $tag;
    }
    public static function lookup_all() {
        return self::$registered;
    }
    public static function register($fdef) {
        PaperColumn::register($fdef);
        self::$registered[] = $fdef;
    }
    public function prepare(PaperList $pl, $visible) {
        if (!$pl->contact->privChair)
            return false;
        if ($visible)
            $pl->qopts["tags"] = 1;
        return true;
    }
    public function header($pl, $row, $ordinal) {
        return "#~" . $this->tag . " tags";
    }
    public function content_empty($pl, $row) {
        return !$pl->contact->can_view_tags($row, true);
    }
    public function content($pl, $row, $rowidx) {
        if (($t = $row->paperTags) === "")
            return "";
        $a = array();
        foreach (pcMembers() as $pcm) {
            $mytag = " " . $pcm->contactId . "~" . $this->tag . "#";
            if (($p = strpos($t, $mytag)) !== false) {
                $n = (int) substr($t, $p + strlen($mytag));
                $a[] = $pcm->name_html() . ($n ? " (#$n)" : "");
            }
        }
        return join(", ", $a);
    }
}

class TimestampPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("timestamp", Column::VIEW_COLUMN | Column::COMPLETABLE,
                            array("sorter" => "update_time_sorter"));
    }
    public function update_time_sorter($a, $b) {
        $at = max($a->timeFinalSubmitted, $a->timeSubmitted, 0);
        $bt = max($b->timeFinalSubmitted, $b->timeSubmitted, 0);
        return $at > $bt ? -1 : ($at == $bt ? 0 : 1);
    }
    public function header($pl, $row, $ordinal) {
        return "Timestamp";
    }
    public function content_empty($pl, $row) {
        return max($row->timeFinalSubmitted, $row->timeSubmitted) <= 0;
    }
    public function content($pl, $row, $rowidx) {
        global $Conf;
        $t = max($row->timeFinalSubmitted, $row->timeSubmitted, 0);
        if ($t > 0)
            return $Conf->printableTimestamp($t);
        else
            return "";
    }
}

class NumericOrderPaperColumn extends PaperColumn {
    private $order;
    public function __construct($order) {
        parent::__construct("numericorder", Column::VIEW_NONE,
                            array("sorter" => "numeric_order_sorter"));
        $this->order = $order;
    }
    public function numeric_order_sorter($a, $b) {
        return @$this->order[$a->paperId] - @$this->order[$b->paperId];
    }
}

class LeadPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("lead", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        return $pl->contact->can_view_lead(null, true);
    }
    public function header($pl, $row, $ordinal) {
        return "Discussion lead";
    }
    public function content_empty($pl, $row) {
        return !$row->leadContactId
            || !$pl->contact->can_view_lead($row, true);
    }
    public function content($pl, $row, $rowidx) {
        $visible = $pl->contact->can_view_lead($row, null);
        return $pl->_contentPC($row, $row->leadContactId, $visible);
    }
}

class ShepherdPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("shepherd", Column::VIEW_ROW | Column::FOLDABLE | Column::COMPLETABLE);
    }
    public function prepare(PaperList $pl, $visible) {
        global $Conf;
        return $pl->contact->isPC
            || ($Conf->has_any_accepts() && $Conf->timeAuthorViewDecision());
    }
    public function header($pl, $row, $ordinal) {
        return "Shepherd";
    }
    public function content_empty($pl, $row) {
        return !$row->shepherdContactId
            || !$pl->contact->can_view_shepherd($row, true);
        // XXX external reviewer can view shepherd even if external reviewer
        // cannot view reviewer identities? WHO GIVES A SHIT
    }
    public function content($pl, $row, $rowidx) {
        $visible = $pl->contact->can_view_shepherd($row, null);
        return $pl->_contentPC($row, $row->shepherdContactId, $visible);
    }
}

class FoldAllPaperColumn extends PaperColumn {
    public function __construct() {
        parent::__construct("foldall", Column::VIEW_NONE);
    }
    public function prepare(PaperList $pl, $visible) {
        $pl->qopts["foldall"] = true;
        return true;
    }
}

function initialize_paper_columns() {
    global $Conf;

    PaperColumn::register(new SelectorPaperColumn("sel", array("minimal" => true)));
    PaperColumn::register(new SelectorPaperColumn("selon", array("minimal" => true, "cssname" => "sel")));
    PaperColumn::register(new SelectorPaperColumn("selconf", array("cssname" => "confselector")));
    PaperColumn::register(new SelectorPaperColumn("selunlessconf", array("minimal" => true, "cssname" => "sel")));
    PaperColumn::register(new IdPaperColumn);
    PaperColumn::register(new TitlePaperColumn);
    PaperColumn::register(new StatusPaperColumn("status", false));
    PaperColumn::register(new StatusPaperColumn("statusfull", true));
    PaperColumn::register(new ReviewerTypePaperColumn("revtype"));
    PaperColumn::register(new ReviewStatusPaperColumn);
    PaperColumn::register(new ReviewSubmittedPaperColumn);
    PaperColumn::register(new ReviewDelegationPaperColumn);
    PaperColumn::register(new AssignReviewPaperColumn);
    PaperColumn::register(new TopicScorePaperColumn);
    PaperColumn::register(new TopicListPaperColumn);
    PaperColumn::register(new PreferencePaperColumn("revpref", false));
    PaperColumn::register_synonym("pref", "revpref");
    PaperColumn::register(new PreferencePaperColumn("editrevpref", true));
    PaperColumn::register_synonym("editpref", "editrevpref");
    PaperColumn::register(new PreferenceListPaperColumn("allrevpref", false));
    PaperColumn::register_synonym("allpref", "allrevpref");
    PaperColumn::register(new PreferenceListPaperColumn("allrevtopicpref", true));
    PaperColumn::register_synonym("alltopicpref", "allrevtopicpref");
    PaperColumn::register(new DesirabilityPaperColumn);
    PaperColumn::register(new ReviewerListPaperColumn);
    PaperColumn::register(new AuthorsPaperColumn);
    PaperColumn::register(new CollabPaperColumn);
    PaperColumn::register(new TagListPaperColumn);
    PaperColumn::register(new AbstractPaperColumn);
    PaperColumn::register(new LeadPaperColumn);
    PaperColumn::register(new ShepherdPaperColumn);
    PaperColumn::register(new PCConflictListPaperColumn);
    PaperColumn::register(new ConflictMatchPaperColumn("authorsmatch", "authorInformation"));
    PaperColumn::register(new ConflictMatchPaperColumn("collabmatch", "collaborators"));
    PaperColumn::register(new TimestampPaperColumn);
    PaperColumn::register(new FoldAllPaperColumn);
    PaperColumn::register_factory("tag:", new TagPaperColumn(null, null, false));
    PaperColumn::register_factory("tagval:", new TagPaperColumn(null, null, true));
    PaperColumn::register_factory("edittag:", new EditTagPaperColumn(null, null, false));
    PaperColumn::register_factory("edittagval:", new EditTagPaperColumn(null, null, true));
    PaperColumn::register_factory("#", new TagPaperColumn(null, null, false));
    PaperColumn::register_factory("edit#", new EditTagPaperColumn(null, null, true));

    foreach (ReviewForm::all_fields() as $f)
        if ($f->has_options) {
            PaperColumn::register_factory("", new ScorePaperColumn(null));
            break;
        }

    if ($Conf && $Conf->setting("formulas")) {
        $result = Dbl::q("select * from Formula order by lower(name)");
        while ($result && ($row = $result->fetch_object("Formula"))) {
            $fid = $row->formulaId;
            FormulaPaperColumn::register(new FormulaPaperColumn("formula$fid", $row));
        }
    }
    PaperColumn::register_factory("", new FormulaPaperColumn("", null));

    $tagger = new Tagger;
    if ($Conf && (TagInfo::has_vote() || TagInfo::has_approval() || TagInfo::has_rank())) {
        $vt = array();
        foreach (TagInfo::defined_tags() as $v)
            if ($v->vote || $v->approval || $v->rank)
                $vt[] = $v->tag;
        foreach ($vt as $n)
            TagReportPaperColumn::register(new TagReportPaperColumn($n));
    }
}

initialize_paper_columns();
