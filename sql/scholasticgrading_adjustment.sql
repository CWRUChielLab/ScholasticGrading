--
-- Adjustments for the ScholasticGrading extension
--
CREATE TABLE /*_*/scholasticgrading_adjustment (
    sgadj_id int unsigned NOT NULL AUTO_INCREMENT,
    sgadj_user_id int(10) unsigned NOT NULL,
    sgadj_title varchar(255) NOT NULL default '',
    sgadj_score decimal(8,4) NOT NULL default 0,
    sgadj_value decimal(8,4) NOT NULL default 0,
    sgadj_enabled boolean NOT NULL default TRUE,
    sgadj_date char(10) NOT NULL default '',
    sgadj_comment varchar(255) NOT NULL default '',
    PRIMARY KEY (sgadj_id),
    FOREIGN KEY (sgadj_user_id) REFERENCES /*_*/user (user_id) ON DELETE CASCADE ON UPDATE CASCADE
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/sgadj_user ON /*_*/scholasticgrading_adjustment (sgadj_user_id);
