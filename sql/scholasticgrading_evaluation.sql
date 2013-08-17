--
-- Evaluations for the ScholasticGrading extension
--
CREATE TABLE /*_*/scholasticgrading_evaluation (
    sge_user_id int(10) unsigned NOT NULL DEFAULT '0',
    sge_assignment_id int unsigned NOT NULL,
    sge_score decimal(8,4) NOT NULL default 0,
    sge_enabled boolean NOT NULL default TRUE,
    sge_date char(10) NOT NULL default '',
    sge_comment varchar(255) NOT NULL default '',
    PRIMARY KEY (sge_user_id, sge_assignment_id),
    FOREIGN KEY (sge_user_id) REFERENCES /*_*/user (user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (sge_assignment_id) REFERENCES /*_*/scholasticgrading_assignment (sga_id) ON DELETE CASCADE ON UPDATE CASCADE
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/sge_user ON /*_*/scholasticgrading_evaluation (sge_user_id);
CREATE INDEX /*i*/sge_assignment ON /*_*/scholasticgrading_evaluation (sge_assignment_id);
