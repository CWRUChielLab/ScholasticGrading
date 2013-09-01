--
-- Assignment group membership for the ScholasticGrading extension
--
CREATE TABLE /*_*/scholasticgrading_groupassignment (
    sgga_group_id int unsigned NOT NULL,
    sgga_assignment_id int unsigned NOT NULL,
    PRIMARY KEY (sgga_group_id, sgga_assignment_id),
    FOREIGN KEY (sgga_group_id) REFERENCES /*_*/scholasticgrading_group (sgg_id) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (sgga_assignment_id) REFERENCES /*_*/scholasticgrading_assignment (sga_id) ON DELETE CASCADE ON UPDATE CASCADE
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/sgga_group ON /*_*/scholasticgrading_groupassignment (sgga_group_id);
CREATE INDEX /*i*/sgga_assignment ON /*_*/scholasticgrading_groupassignment (sgga_assignment_id);
